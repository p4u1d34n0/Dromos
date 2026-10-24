<?php

namespace Dromos\Http\Message;

use Dromos\Http\Message\RequestInterface;
use Dromos\Http\Message\UploadedFileInterface;

/**
 * Representation of an incoming, server-side HTTP request, as defined by PSR-7.
 */
interface ServerRequestInterface extends RequestInterface
{
    /**
     * Retrieve server parameters, typically derived from PHP's $_SERVER.
     *
     * @return array
     */
    public function getServerParams(): array;

    /**
     * Retrieve cookies sent by the client to the server.
     *
     * @return array
     */
    public function getCookieParams(): array;

    /**
     * Return an instance with the specified cookies.
     *
     * @param array $cookies
     * @return static
     */
    public function withCookieParams(array $cookies): static;

    /**
     * Retrieve query string arguments.
     *
     * @return array
     */
    public function getQueryParams(): array;

    /**
     * Return an instance with the specified query string arguments.
     *
     * @param array $query
     * @return static
     */
    public function withQueryParams(array $query): static;

    /**
     * Retrieve normalized file upload data.
     *
     * Each leaf is an instance of UploadedFileInterface.
     *
     * @return UploadedFileInterface[]
     */
    public function getUploadedFiles(): array;

    /**
     * Return an instance with the specified uploaded files.
     *
     * @param UploadedFileInterface[] $uploadedFiles
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles): static;

    /**
     * Retrieve any parameters provided in the request body.
     *
     * @return null|array|object
     */
    public function getParsedBody(): null|array|object;

    /**
     * Return an instance with the specified body parameters.
     *
     * @param null|array|object $data
     * @return static
     */
    public function withParsedBody(null|array|object $data): static;

    /**
     * Retrieve attributes derived from the request (e.g., via routing).
     *
     * @return array
     */
    public function getAttributes(): array;

    /**
     * Retrieve a single derived request attribute.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * Return an instance with the specified derived request attribute.
     *
     * @param string $name
     * @param mixed  $value
     * @return static
     */
    public function withAttribute(string $name, mixed $value): static;

    /**
     * Return an instance that removes the specified derived request attribute.
     *
     * @param string $name
     * @return static
     */
    public function withoutAttribute(string $name): static;
}
