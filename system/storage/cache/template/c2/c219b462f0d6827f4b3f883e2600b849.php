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

/* admin/view/template/catalog/product_report.twig */
class __TwigTemplate_921d29016c0c2a778ff11e42c1af13f4 extends Template
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
        yield "<div class=\"table-responsive\">
  <table class=\"table table-bordered table-hover\">
    <thead>
      <tr>
        <th>";
        // line 5
        yield ($context["column_ip"] ?? null);
        yield "</th>
        <th>";
        // line 6
        yield ($context["column_store"] ?? null);
        yield "</th>
        <th>";
        // line 7
        yield ($context["column_country"] ?? null);
        yield "</th>
        <th>";
        // line 8
        yield ($context["column_date_added"] ?? null);
        yield "</th>
      </tr>
    </thead>
    <tbody>
      ";
        // line 12
        if (($context["reports"] ?? null)) {
            // line 13
            yield "        ";
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["reports"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["report"]) {
                // line 14
                yield "          <tr>
            <td><a href=\"https://whatismyipaddress.com/ip/";
                // line 15
                yield CoreExtension::getAttribute($this->env, $this->source, $context["report"], "ip", [], "any", false, false, false, 15);
                yield "\" target=\"_blank\">";
                yield CoreExtension::getAttribute($this->env, $this->source, $context["report"], "ip", [], "any", false, false, false, 15);
                yield "</a></td>
            <td>";
                // line 16
                yield CoreExtension::getAttribute($this->env, $this->source, $context["report"], "store", [], "any", false, false, false, 16);
                yield "</td>
            <td>";
                // line 17
                yield CoreExtension::getAttribute($this->env, $this->source, $context["report"], "country", [], "any", false, false, false, 17);
                yield "</td>
            <td>";
                // line 18
                yield CoreExtension::getAttribute($this->env, $this->source, $context["report"], "date_added", [], "any", false, false, false, 18);
                yield "</td>
          </tr>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_key'], $context['report'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 21
            yield "      ";
        } else {
            // line 22
            yield "        <tr>
          <td class=\"text-center\" colspan=\"4\">";
            // line 23
            yield ($context["text_no_results"] ?? null);
            yield "</td>
        </tr>
      ";
        }
        // line 26
        yield "    </tbody>
  </table>
</div>
<div class=\"row\">
  <div class=\"col-sm-6 text-start\">";
        // line 30
        yield ($context["pagination"] ?? null);
        yield "</div>
  <div class=\"col-sm-6 text-end\">";
        // line 31
        yield ($context["results"] ?? null);
        yield "</div>
</div>
";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "admin/view/template/catalog/product_report.twig";
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
        return array (  122 => 31,  118 => 30,  112 => 26,  106 => 23,  103 => 22,  100 => 21,  91 => 18,  87 => 17,  83 => 16,  77 => 15,  74 => 14,  69 => 13,  67 => 12,  60 => 8,  56 => 7,  52 => 6,  48 => 5,  42 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("<div class=\"table-responsive\">
  <table class=\"table table-bordered table-hover\">
    <thead>
      <tr>
        <th>{{ column_ip }}</th>
        <th>{{ column_store }}</th>
        <th>{{ column_country }}</th>
        <th>{{ column_date_added }}</th>
      </tr>
    </thead>
    <tbody>
      {% if reports %}
        {% for report in reports %}
          <tr>
            <td><a href=\"https://whatismyipaddress.com/ip/{{ report.ip }}\" target=\"_blank\">{{ report.ip }}</a></td>
            <td>{{ report.store }}</td>
            <td>{{ report.country }}</td>
            <td>{{ report.date_added }}</td>
          </tr>
        {% endfor %}
      {% else %}
        <tr>
          <td class=\"text-center\" colspan=\"4\">{{ text_no_results }}</td>
        </tr>
      {% endif %}
    </tbody>
  </table>
</div>
<div class=\"row\">
  <div class=\"col-sm-6 text-start\">{{ pagination }}</div>
  <div class=\"col-sm-6 text-end\">{{ results }}</div>
</div>
", "admin/view/template/catalog/product_report.twig", "/Users/valentyn/Sites/opencart4/admin/view/template/catalog/product_report.twig");
    }
}
