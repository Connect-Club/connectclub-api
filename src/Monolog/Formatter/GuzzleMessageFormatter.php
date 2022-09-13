<?php

namespace App\Monolog\Formatter;

use GuzzleHttp\MessageFormatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleMessageFormatter extends MessageFormatter
{
    private array $loggingContext;

    /**
     * MessageFormatter constructor.
     */
    public function __construct(array $loggingContext = [], string $template = \GuzzleHttp\MessageFormatter::CLF)
    {
        $this->loggingContext = $loggingContext;

        parent::__construct($template);
    }

    public function format(
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $error = null
    ): string {

        $array = [];
        $array['request'] = $array['response'] = [];
        $array['request']['headers'] = $array['response']['headers'] = [];

        $requestHeaders = [];
        foreach ($request->getHeaders() as $header => $headerValue) {
            $requestHeaders[$header] = implode(' ', $headerValue);
        }
        $array['request']['headers'] = json_encode($requestHeaders);

        $request->getBody()->rewind();

        $array['request']['method'] = $request->getMethod();
        $array['request']['uri'] = (string) $request->getUri();
        $array['request']['body'] = (string) $request->getBody();
        $array['request']['protocol'] = $request->getProtocolVersion();

        if ($response) {
            $response->getBody()->rewind();

            $array['response']['body'] = (string) $response->getBody();
            $array['response']['statusCode'] = $response->getStatusCode();
            $array['response']['phrase'] = $response->getReasonPhrase();

            $responseHeaders = [];
            foreach ($response->getHeaders() as $header => $headerValue) {
                $responseHeaders[$header] = implode(' ', $headerValue);
            }
            $array['response']['headers'] = json_encode($responseHeaders);

            $response->getBody()->rewind();
        }

        if ($error) {
            $array['error'] = [
                'code' => $error->getCode(),
                'message' => $error->getMessage(),
            ];
        }

        $array = array_merge($array, $this->loggingContext);

        $request->getBody()->rewind();

        return sprintf('Request to %s %s', $array['request']['method'], $array['request']['uri']).json_encode($array);
    }
}
