<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* catalog/view/template/common/pagination.twig */
class __TwigTemplate_c0ef987d9e9500593ebc639803601b0a extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield "<ul class=\"pagination\">
  ";
        // line 2
        if (($context["first"] ?? null)) {
            // line 3
            yield "    <li class=\"page-item\"><a href=\"";
            yield ($context["first"] ?? null);
            yield "\" class=\"page-link\">|&lt;</a></li>
  ";
        }
        // line 5
        yield "  ";
        if (($context["prev"] ?? null)) {
            // line 6
            yield "    <li class=\"page-item\"><a href=\"";
            yield ($context["prev"] ?? null);
            yield "\" class=\"page-link\">&lt;</a></li>
  ";
        }
        // line 8
        yield "  ";
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["links"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["link"]) {
            // line 9
            yield "    ";
            if ((CoreExtension::getAttribute($this->env, $this->source, $context["link"], "page", [], "any", false, false, false, 9) == ($context["page"] ?? null))) {
                // line 10
                yield "      <li class=\"page-item active\"><span class=\"page-link\">";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["link"], "page", [], "any", false, false, false, 10);
                yield "</span></li>
    ";
            } else {
                // line 12
                yield "      <li class=\"page-item\"><a href=\"";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["link"], "href", [], "any", false, false, false, 12);
                yield "\" class=\"page-link\">";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["link"], "page", [], "any", false, false, false, 12);
                yield "</a></li>
    ";
            }
            // line 14
            yield "  ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_key'], $context['link'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 15
        yield "  ";
        if (($context["next"] ?? null)) {
            // line 16
            yield "    <li class=\"page-item\"><a href=\"";
            yield ($context["next"] ?? null);
            yield "\" class=\"page-link\">&gt;</a></li>
  ";
        }
        // line 18
        yield "  ";
        if (($context["last"] ?? null)) {
            // line 19
            yield "    <li class=\"page-item\"><a href=\"";
            yield ($context["last"] ?? null);
            yield "\" class=\"page-link\">&gt;|</a></li>
  ";
        }
        // line 21
        yield "</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "catalog/view/template/common/pagination.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  108 => 21,  102 => 19,  99 => 18,  93 => 16,  90 => 15,  84 => 14,  76 => 12,  70 => 10,  67 => 9,  62 => 8,  56 => 6,  53 => 5,  47 => 3,  45 => 2,  42 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("<ul class=\"pagination\">
  {% if first %}
    <li class=\"page-item\"><a href=\"{{ first }}\" class=\"page-link\">|&lt;</a></li>
  {% endif %}
  {% if prev %}
    <li class=\"page-item\"><a href=\"{{ prev }}\" class=\"page-link\">&lt;</a></li>
  {% endif %}
  {% for link in links %}
    {% if link.page == page %}
      <li class=\"page-item active\"><span class=\"page-link\">{{ link.page }}</span></li>
    {% else %}
      <li class=\"page-item\"><a href=\"{{ link.href }}\" class=\"page-link\">{{ link.page }}</a></li>
    {% endif %}
  {% endfor %}
  {% if next %}
    <li class=\"page-item\"><a href=\"{{ next }}\" class=\"page-link\">&gt;</a></li>
  {% endif %}
  {% if last %}
    <li class=\"page-item\"><a href=\"{{ last }}\" class=\"page-link\">&gt;|</a></li>
  {% endif %}
</ul>", "catalog/view/template/common/pagination.twig", "/Users/valentyn/Sites/opencart4/catalog/view/template/common/pagination.twig");
    }
}
