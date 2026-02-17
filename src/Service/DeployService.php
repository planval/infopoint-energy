<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

class DeployService
{
    private LoggerInterface $logger;
    private array $configs;
    private \GuzzleHttp\Client $httpClient;
    private FinancialSupportExportService $financialSupportExportService;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger, FinancialSupportExportService $financialSupportExportService)
    {
        $this->logger = $logger;
        $this->configs = [
            'staging' => $this->parseDeploymentUrl($params->get('deployment_staging')),
            'production' => $this->parseDeploymentUrl($params->get('deployment_production')),
        ];
        $this->httpClient = new \GuzzleHttp\Client();
        $this->financialSupportExportService = $financialSupportExportService;
    }

    private function parseDeploymentUrl(string $url): array
    {
        $parts = parse_url($url);

        if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'])) {
            throw new \InvalidArgumentException("Invalid URL format: {$url}");
        }

        parse_str($parts['query'] ?? '', $params);

        return [
            'scheme' => strtolower($parts['scheme'] ?? 'https'),
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 21,
            'username' => urldecode($parts['user'] ?? ''),
            'password' => urldecode($parts['pass'] ?? ''),
            'base_path' => rtrim($parts['path'] ?? '', '/'),
            'timeout' => 60,
            'passive' => true,
            'params' => $params,
        ];
    }

    public function deploy(string $environment): array
    {

        $cfg = $this->configs[$environment] ?? null;
        if (!$cfg) return ['success' => false, 'error' => "Missing config for {$environment}"];

        try {

            $zipFilePath = $this->financialSupportExportService->exportAllToZip();

            $formFields = [
                'zip' => DataPart::fromPath($zipFilePath),
            ];

            $formData = new FormDataPart($formFields);

            $response = $this->httpClient->request('POST', $this->configs[$environment]['scheme'].'://'.$this->configs[$environment]['host'], [
                'headers' => [
                    'X-PSK' => $this->configs[$environment]['params']['psk'] ?? null,
                    ...$formData->getPreparedHeaders()->toArray(),
                ],
                'body' => $formData->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getBody();

            if($statusCode !== 200) {
                throw new \Exception('Deployment failed');
            }

        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => $exception->getMessage(), 'statusCode' => $statusCode ?? null, 'content' => $content ?? null];
        } finally {
            if($zipFilePath ?? null && str_starts_with($zipFilePath, '/') && str_ends_with($zipFilePath, '.zip') && is_file($zipFilePath)) {
                @unlink($zipFilePath);
            }
        }

        return ['success' => true, 'message' => 'Deployment completed', 'statusCode' => $statusCode, 'content' => $content];

    }

}