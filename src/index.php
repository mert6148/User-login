<?php

use App\Helpers\TemplateHelper;
use App\Models\Template;

class Templates
{
    protected $templateHelper;

    public function __construct(TemplateHelper $templateHelper)
    {
        $this->templateHelper = $templateHelper;
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
}
