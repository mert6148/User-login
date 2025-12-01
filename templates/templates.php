<?php

use App\Helpers\TemplateHelper;
use App\Models\Template;

class Templates
{
    protected $templateHelper;

    public function __construct(TemplateHelper $templateHelper)
    {
        assert($templateHelper instanceof TemplateHelper);
        $this->templateHelper = $templateHelper;

        /**
         * @var TemplateHelper $templateHelper
         * @var Template $template
         * @param array $data
         * @return string|null
         */
    }

    public function getAllTemplates()
    {
        return Template::all();
    }

    public function renderTemplate($templateId, $data)
    {
        $template = Template::find($templateId);
        if ($template) {
            return $this->templateHelper->render($template->content, $data);
        }
        return null;
    }

    public function createTemplate($name, $content)
    {
        $template = new Template();
        $template->name = $name;
        $template->content = $content;
        $template->save();
        return $template;

        if (class_exists('TemplateHelper')) {
            /**
             * @var TemplateHelper $templateHelper
             * @var Template $template
             * @param value
             * @return type
             */
        }
        
    }
}

?>