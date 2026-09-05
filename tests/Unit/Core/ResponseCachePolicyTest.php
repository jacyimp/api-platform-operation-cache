<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[CoversClass(ResponseCachePolicy::class)]
final class ResponseCachePolicyTest extends TestCase
{
    private ResponseCachePolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ResponseCachePolicy();
    }

    public function testItAllowsGetRequests(): void
    {
        self::assertTrue(
            $this->policy->allowsRequest(
                Request::create('/', Request::METHOD_GET),
            ),
        );
    }

    public function testItAllowsHeadRequests(): void
    {
        self::assertTrue(
            $this->policy->allowsRequest(
                Request::create('/', Request::METHOD_HEAD),
            ),
        );
    }

    public function testItRejectsNonCacheableMethods(): void
    {
        foreach (
            [
                Request::METHOD_POST,
                Request::METHOD_PUT,
                Request::METHOD_PATCH,
                Request::METHOD_DELETE,
            ] as $method
        ) {
            self::assertFalse(
                $this->policy->allowsRequest(
                    Request::create('/', $method),
                ),
                sprintf('Method %s should not be cacheable.', $method),
            );
        }
    }

    public function testItAllowsSuccessfulResponse(): void
    {
        self::assertTrue(
            $this->policy->allowsResponse(
                new Response('{}', Response::HTTP_OK),
            ),
        );
    }

    public function testItAllowsOtherSuccessfulStatuses(): void
    {
        self::assertTrue(
            $this->policy->allowsResponse(
                new Response('', Response::HTTP_NO_CONTENT),
            ),
        );
    }

    public function testItRejectsNonSuccessfulResponse(): void
    {
        foreach (
            [
                Response::HTTP_MOVED_PERMANENTLY,
                Response::HTTP_BAD_REQUEST,
                Response::HTTP_NOT_FOUND,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ] as $status
        ) {
            self::assertFalse(
                $this->policy->allowsResponse(
                    new Response('', $status),
                ),
                sprintf('Status %d should not be cacheable.', $status),
            );
        }
    }

    public function testItRejectsStreamedResponse(): void
    {
        self::assertFalse(
            $this->policy->allowsResponse(
                new StreamedResponse(
                    static function (): void {
                    },
                ),
            ),
        );
    }

    public function testItRejectsBinaryFileResponse(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'operation-cache-');

        self::assertNotFalse($file);

        try {
            file_put_contents($file, 'content');

            self::assertFalse(
                $this->policy->allowsResponse(
                    new BinaryFileResponse($file),
                ),
            );
        } finally {
            @unlink($file);
        }
    }

    public function testItRejectsNoStoreResponse(): void
    {
        $response = new Response('{}');
        $response->headers->set(
            'Cache-Control',
            'public, no-store',
        );

        self::assertFalse(
            $this->policy->allowsResponse($response),
        );
    }

    public function testItRejectsResponseThatSetsCookie(): void
    {
        $response = new Response('{}');
        $response->headers->set(
            'Set-Cookie',
            'session=abc',
        );

        self::assertFalse(
            $this->policy->allowsResponse($response),
        );
    }

    public function testItRejectsWildcardVary(): void
    {
        $response = new Response('{}');
        $response->headers->set('Vary', '*');

        self::assertFalse(
            $this->policy->allowsResponse($response),
        );
    }

    public function testItRejectsWildcardAmongMultipleVaryValues(): void
    {
        $response = new Response('{}');
        $response->headers->set(
            'Vary',
            'Accept-Language, *, Accept',
        );

        self::assertFalse(
            $this->policy->allowsResponse($response),
        );
    }

    public function testItAllowsNormalVaryHeader(): void
    {
        $response = new Response('{}');
        $response->headers->set(
            'Vary',
            'Accept-Language, Accept',
        );

        self::assertTrue(
            $this->policy->allowsResponse($response),
        );
    }
}
