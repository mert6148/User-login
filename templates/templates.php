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
    }

    public function updateTemplate($templateId, $name, $content)
    {
        $template = Template::find($templateId);
        if ($template) {
            $template->name = $name;
            $template->content = $content;
            $template->save();
            return $template;
        }
        return null;
    }

    public function deleteTemplate($templateId)
    {
        $template = Template::find($templateId);
        if ($template) {
            $template->delete();
            return true;
        }
        return false;
    }
}

class TemplateHelper
{
    public function __construct()
    {
        // Constructor logic
    }

    public function createTemplate($name, $content)
    {
        $template = new Template();
        $template->name = $name;
        $template->content = $content;
        $template->save();
        return $template;
    }

    public function updateTemplate($templateId, $name, $content)
    {
        $template = Template::find($templateId);
        if ($template) {
            $template->name = $name;
            $template->content = $content;
            $template->save();
            return $template;
        }
        return null;
    }

    public function deleteTemplate($templateId)
    {
        $template = Template::find($templateId);
        if ($template) {
            $template->delete();
            return true;
        }
        return false;
    }
    public function render($template, $data)
    {
        $content = $template;
        foreach ($data as $key => $value) {
            $content = str_replace("{{ $key }}", $value, $content);
        }
        return $content;
    }

    public function renderTemplate($templateId, $data)
    {
        $template = Template::find($templateId);
        if ($template) {
            return $this->render($template->content, $data);
        }
        return null;
    }

    public function getTemplate($templateId)
    {
        $template = Template::find($templateId);
        if ($template) {
            return $template;
        }
        return null;
    }

}

?>