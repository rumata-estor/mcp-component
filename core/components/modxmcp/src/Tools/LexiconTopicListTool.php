<?php
namespace ModxMcp\Tools;

class LexiconTopicListTool implements ToolInterface
{
    public function name() { return 'list_lexicon_topics'; }
    public function group() { return 'lexicon'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return ProcessorSupport::run(
            $context, 'workspace/lexicon/topic/getlist', $data, true,
            array('core:default', 'core:workspaces')
        );
    }
}
