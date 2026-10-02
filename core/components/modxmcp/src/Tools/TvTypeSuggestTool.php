<?php
namespace ModxMcp\Tools;

class TvTypeSuggestTool implements ToolInterface
{
    public function name() { return 'suggest_tv_type'; }
    public function group() { return 'tv_inputs'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $desc = isset($data['description']) ? mb_strtolower((string) $data['description'], 'UTF-8') : '';
        if (trim($desc) === '') { throw new \ModxMCPClientException('suggest_tv_type: "description" is required.'); }

        $rules = array(
            array('type' => 'migx',         'why' => 'repeating structured rows (each with several fields)', 'kw' => array('migx', 'repeat', 'rows', 'gallery', 'slides', 'slider', 'carousel', 'list of items', 'blocks', 'multiple items', 'галере', 'слайд', 'слайдер', 'карусел', 'повторя', 'список блок', 'несколько элемент', 'список товар', 'список услуг')),
            array('type' => 'image',        'why' => 'a single image', 'kw' => array('image', 'photo', 'picture', 'logo', 'banner', 'icon', 'thumbnail', 'изображ', 'картинк', 'фото', 'логотип', 'баннер', 'иконк', 'превью')),
            array('type' => 'file',         'why' => 'a downloadable file', 'kw' => array('file', 'document', 'pdf', 'download', 'attachment', 'файл', 'документ', 'скачать', 'вложен')),
            array('type' => 'colorpicker',  'why' => 'a color value', 'kw' => array('color', 'colour', 'цвет', 'окрас')),
            array('type' => 'date',         'why' => 'a date / time value', 'kw' => array('date', 'time', 'datetime', 'deadline', 'schedule', 'дата', 'время', 'срок', 'расписан')),
            array('type' => 'number',       'why' => 'a numeric value', 'kw' => array('number', 'numeric', 'count', 'quantity', 'qty', 'amount', 'price', 'integer', 'weight', 'rating', 'число', 'количеств', 'цена', 'сумма', 'вес', 'рейтинг')),
            array('type' => 'email',        'why' => 'an email address', 'kw' => array('email', 'e-mail', 'почт', 'эл. адрес')),
            array('type' => 'resourcelist', 'why' => 'a link to another MODX resource (page)', 'kw' => array('related page', 'link to a page', 'resource', 'parent page', 'choose a page', 'связанн', 'ссылка на страниц', 'ресурс', 'выбрать страниц', 'родительск')),
            array('type' => 'url',          'why' => 'a URL / web link', 'kw' => array('url', 'website', 'web link', 'external link', 'href', 'ссылк', 'сайт', 'веб-адрес')),
            array('type' => 'richtext',     'why' => 'formatted content via the WYSIWYG editor', 'kw' => array('rich', 'wysiwyg', 'formatted', 'html content', 'article body', 'content body', 'editor', 'форматир', 'визуальн', 'редактор', 'статья', 'контент тела', 'вёрстк')),
            array('type' => 'listbox-multiple', 'why' => 'pick SEVERAL values from a list', 'kw' => array('multiple', 'several', 'multi-select', 'multiselect', 'many of', 'несколько из', 'мультивыбор', 'много значен')),
            array('type' => 'listbox',      'why' => 'pick ONE value from a fixed list', 'kw' => array('select', 'dropdown', 'choose one', 'option', 'status', 'choice', 'list of values', 'выбор', 'выпадающ', 'один из', 'опци', 'статус', 'вариант')),
            array('type' => 'radio',        'why' => 'pick ONE value as radio buttons', 'kw' => array('radio', 'радио', 'переключател')),
            array('type' => 'checkbox',     'why' => 'on/off toggle(s)', 'kw' => array('checkbox', 'yes/no', 'yes no', 'boolean', 'toggle', 'flag', 'on/off', 'enable', 'чекбокс', 'да/нет', 'да нет', 'флаг', 'вкл', 'переключ', 'булев')),
            array('type' => 'tag',          'why' => 'comma-separated tags', 'kw' => array('tag', 'tags', 'keywords', 'тег', 'метк', 'ключев слов')),
            array('type' => 'textarea',     'why' => 'multi-line plain text', 'kw' => array('textarea', 'multi-line', 'multiline', 'paragraph', 'description', 'note', 'многострочн', 'абзац', 'описани', 'заметк')),
            array('type' => 'rawtextarea',  'why' => 'raw HTML/code, not output-filtered', 'kw' => array('raw html', 'embed code', 'script', 'iframe', 'код', 'встроить код', 'сырой html')),
            array('type' => 'text',         'why' => 'short single-line text', 'kw' => array('title', 'name', 'label', 'heading', 'short text', 'css class', 'заголов', 'назван', 'короткий текст', 'css класс', 'метка')),
        );

        $scored = array();
        foreach ($rules as $i => $r) {
            $score = 0; $hits = array();
            foreach ($r['kw'] as $kw) {
                if (mb_strpos($desc, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) { $score++; $hits[] = $kw; }
            }
            if ($score > 0) { $scored[] = array('type' => $r['type'], 'why' => $r['why'], 'score' => $score, 'matched' => $hits, 'order' => $i); }
        }
        usort($scored, function ($a, $b) {
            if ($a['score'] !== $b['score']) { return $b['score'] - $a['score']; }
            return $a['order'] - $b['order'];
        });

        $types = TvInputTypeListTool::catalog($context);
        $colorAvailable = isset($types['custom']['colorpicker']);
        $note = null;
        foreach ($scored as $k => $c) {
            if ($c['type'] === 'colorpicker' && !$colorAvailable) {
                $scored[$k]['type'] = 'text';
                $scored[$k]['why'] = 'a color value (no colorpicker add-on installed — use text, or install one)';
                $note = 'No colorpicker input type is installed; suggesting text. Install a colorpicker add-on for a real picker.';
            }
        }

        if (!$scored) {
            $scored[] = array('type' => 'text', 'why' => 'no strong signal — short single-line text is the safe default', 'score' => 0, 'matched' => array());
        }

        $top = $scored[0]['type'];
        $reqMap = array(
            'image' => array('media_source' => '<media source id — see modx_list_media_sources>'),
            'file'  => array('media_source' => '<media source id — see modx_list_media_sources>'),
            'listbox' => array('elements' => 'Label==value||Label2==value2'),
            'listbox-multiple' => array('elements' => 'Label==value||Label2==value2'),
            'radio' => array('elements' => 'Label==value||Label2==value2'),
            'checkbox' => array('elements' => 'Yes==1'),
            'resourcelist' => array('input_properties' => array('parents' => '<optional parent id to limit the picker>')),
            'colorpicker' => array('input_properties' => array('format' => 'hex')),
            'migx' => array('input_properties' => array('configs' => '', 'formtabs' => '<see the migx help topic>', 'columns' => '<see the migx help topic>')),
        );
        $skel = array('name' => '<tv_name>', 'caption' => '<Caption>', 'field_type' => $top, 'templates' => array('<template id>'));
        if (isset($reqMap[$top])) { $skel = array_merge($skel, $reqMap[$top]); }

        return array(
            'description' => (string) $data['description'],
            'candidates'  => array_slice($scored, 0, 3),
            'recommended' => $top,
            'create_skeleton' => array('action' => 'create_element', 'type' => 'tv', 'data' => $skel),
            'note' => $note,
            'docs' => 'See the tv_input_types help topic (and migx for repeating rows).',
        );
    }
}
