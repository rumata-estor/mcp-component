<?php
namespace ModxMcp\Tools;

class RunProcessorTool implements ToolInterface
{
    public function name() { return 'run_processor'; }
    public function group() { return 'ops'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        if (!$modx->getOption(
            'modxmcp.allow_run_processor',
            null,
            false
        )) {
            throw new \ModxMCPClientException(
                'run_processor is disabled. Set modxmcp.allow_run_processor = Yes to enable it.'
            );
        }

        $processor = isset($data['processor'])
            ? (string)$data['processor']
            : '';
        if ($processor === '') {
            throw new \ModxMCPClientException(
                'run_processor: "processor" path is required.'
            );
        }
        $properties = isset($data['properties'])
            && is_array($data['properties'])
            ? $data['properties']
            : array();
        $options = array();
        if (!empty($data['processors_path'])) {
            $options['processors_path'] = (string)$data['processors_path'];
        }

        if (
            empty($options['processors_path'])
            && preg_match(
                '#^(context|element|resource|security|source|system|workspace)/#',
                $processor
            )
        ) {
            $response = $context->platform()->runProcessor(
                $modx,
                $processor,
                $properties
            );
            $auditProcessor = $context->platform()->processorTarget($processor);
        } else {
            $response = $modx->runProcessor(
                $processor,
                $properties,
                $options
            );
            $auditProcessor = $processor;
        }

        if (!$response) {
            throw new \ModxMCPClientException(
                'run_processor: no response (processor not found?).'
            );
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(
                ProcessorSupport::error($response)
            );
        }

        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array('processor' => $auditProcessor)
        );
        $decoded = json_decode($response->getResponse(), true);
        return is_array($decoded)
            ? $decoded
            : ProcessorSupport::normalize($response);
    }
}
