<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\ApiPrincipal;
use App\Api\SendRequestValidator;
use App\Services\ApiSendService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class SendMailController
{
    public function __construct(
        private ApiSendService $service,
        private SendRequestValidator $validator,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private int $maximumBodyBytes = 262144
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $requestId = (string) ($request->getAttribute('request_id') ?? 'unavailable');
        try {
            $principal = $request->getAttribute('api_principal');
            if (!$principal instanceof ApiPrincipal) {
                throw new ApiException(401, 'invalid_api_key', 'A valid Bearer API key is required.');
            }
            $this->service->consumeRequestAllowance($principal);
            $mediaType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
            if ($mediaType !== 'application/json') {
                throw new ApiException(415, 'unsupported_media_type', 'Content-Type must be application/json.');
            }
            $declared = $request->getHeaderLine('Content-Length');
            if ($declared !== '' && ctype_digit($declared) && (int) $declared > $this->maximumBodyBytes) {
                throw new ApiException(413, 'request_too_large', 'The JSON request exceeds 256 KiB.');
            }
            $body = (string) $request->getBody();
            if (strlen($body) > $this->maximumBodyBytes) {
                throw new ApiException(413, 'request_too_large', 'The JSON request exceeds 256 KiB.');
            }
            try {
                $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ApiException(400, 'malformed_json', 'The request body is not valid JSON.', null, $exception);
            }
            if (!is_array($decoded) || array_is_list($decoded)) {
                throw new ApiException(422, 'invalid_document', 'The JSON body must be an object.');
            }
            $idempotencyKeys = $request->getHeader('Idempotency-Key');
            if (count($idempotencyKeys) !== 1) {
                throw new ApiException(422, 'invalid_idempotency_key', 'Exactly one Idempotency-Key is required.');
            }
            $validated = $this->validator->validate($decoded);
            $result = $this->service->submit($principal, $validated, $idempotencyKeys[0], $requestId);
            return $this->json(202, $result['response']);
        } catch (ApiException $exception) {
            $payload = [
                'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()],
                'request_id' => $requestId,
            ];
            return $this->json($exception->httpStatus, $payload, $exception->retryAfter);
        } catch (\Throwable) {
            return $this->json(503, [
                'error' => ['code' => 'service_unavailable', 'message' => 'The request could not be durably processed.'],
                'request_id' => $requestId,
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload, ?int $retryAfter = null): ResponseInterface
    {
        $response = $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streams->createStream(json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )));
        return $retryAfter === null ? $response : $response->withHeader('Retry-After', (string) max(1, $retryAfter));
    }
}
