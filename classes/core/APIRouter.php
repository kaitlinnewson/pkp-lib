<?php

/**
 * @file classes/core/APIRouter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIRouter
 *
 * @ingroup core
 *
 * @brief Map HTTP requests to a REST API using the laravel router
 *
 * Requests for [index.php]/api are intercepted for site-level API requests,
 * and requests for [index.php]/{contextPath}/api are intercepted for
 * context-level API requests.
 */

namespace PKP\core;

use APP\core\Application;
use Exception;
use Illuminate\Http\Response;

class APIRouter extends PKPRouter
{
    /**
     * Determines path info parts
     *
     */
    protected function getPathInfoParts(): array
    {
        return explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    }

    /**
     * Determines whether this router can route the given request.
     *
     * @param PKPRequest $request
     *
     * @return bool true, if the router supports this request, otherwise false
     */
    public function supports($request): bool
    {
        $pathInfoParts = $this->getPathInfoParts();

        if (!is_null($pathInfoParts) && count($pathInfoParts) >= 2 && $pathInfoParts[1] == 'api') {
            // Context-specific API requests: [index.php]/{contextPath}/api
            return true;
        }

        return false;
    }

    /**
     * Get the API version
     */
    public function getVersion(): string
    {
        $pathInfoParts = $this->getPathInfoParts();
        return Core::cleanFileVar($pathInfoParts[2] ?? '');
    }

    /**
     * Get the entity being requested
     */
    public function getEntity(): string
    {
        $pathInfoParts = $this->getPathInfoParts();
        return Core::cleanFileVar($pathInfoParts[3] ?? '');
    }

    //
    // Implement template methods from PKPRouter
    //
    /**
     * @copydoc PKPRouter::route()
     */
    public function route(PKPRequest $request): void
    {
        $sourceFile = sprintf('api/%s/%s/index.php', $this->getVersion(), $this->getEntity());

        if (!file_exists($sourceFile)) {
            response()->json([
                'error' => 'api.404.endpointNotFound',
                'errorMessage' => __('api.404.endpointNotFound'),
            ], Response::HTTP_NOT_FOUND)->send();
            exit;
        }

        $handler = require('./' . $sourceFile);
        $this->setHandler($handler);
    }

    /**
     * Get the requested operation
     */
    public function getRequestedOp(PKPRequest $request): string
    {
        if ($routeActionName = PKPBaseController::getRouteActionName()) {
            return $routeActionName;
        }

        return '';
    }

    /**
     * @copydoc PKPRouter::handleAuthorizationFailure()
     */
    public function handleAuthorizationFailure(
        PKPRequest $request,
        string $authorizationMessage,
        array $messageParams = []
    ): void {
        response()->json([
            'error' => $authorizationMessage,
            'errorMessage' => __($authorizationMessage, $messageParams),
        ], Response::HTTP_FORBIDDEN)->send();
        exit;
    }

    /**
     * @copydoc PKPRouter::url()
     */
    public function url(
        PKPRequest $request,
        ?string $newContext = null,
        ?string $endpoint = null,
        ?string $op = null,
        ?array $path = null,
        ?array $params = null,
        ?string $anchor = null,
        bool $escape = false
    ): string {
        // APIHandlers do not understand $op, $path or $anchor. All routing is baked
        // into the $endpoint string. It only accepts a string as the $newContext,
        // since it relies on this when path info is disabled.
        if (!is_null($op) || !is_null($path) || !is_null($anchor) || !is_scalar($newContext)) {
            throw new Exception('APIRouter::url() should not be called with an op, path or anchor. If a new context is passed, the context path must be passed instead of the context object.');
        }

        [$baseUrl, $context] = $this->_urlGetBaseAndContext($request, $newContext);
        $additionalParameters = $this->_urlGetAdditionalParameters($request, $params, $escape);

        return $this->_urlFromParts($baseUrl, [$context, 'api', Application::API_VERSION, $endpoint], $additionalParameters, $anchor, $escape);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\APIRouter', '\APIRouter');
}
