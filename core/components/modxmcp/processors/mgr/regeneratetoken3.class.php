<?php

use MODX\Revolution\Processors\Processor;

class ModxmcpRegenerateToken3Processor extends Processor
{
    public function checkPermissions()
    {
        return $this->modx->hasPermission('settings');
    }

    public function process()
    {
        $modelFile = $this->modx->getOption('core_path') . 'components/modxmcp/model/modxmcp.class.php';
        if (!file_exists($modelFile)) {
            return $this->failure('modxMCP model not found.');
        }

        require_once $modelFile;

        try {
            $result = (new modxMCP($this->modx))->regenerateToken();
        } catch (Exception $e) {
            return $this->failure($e->getMessage());
        } catch (Throwable $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success('', $result);
    }
}

return 'ModxmcpRegenerateToken3Processor';
