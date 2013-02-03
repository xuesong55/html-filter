<?php

namespace HTMLFilter;

class HTMLFilter
{
    private $config;
    private $original_dom;
    private $filterd_dom;
    private $libxml_used_internal_errors;

    public function __construct()
    {
    }

    public function filter(HTMLFilterConfiguration $config, $html_text)
    {
        $this->initialize($config, $html_text);
        $this->copyAllowedNodes();
        $filtered_html = $this->fetchFilteredHTML();
        $this->cleanup();

        return $filtered_html;
    }

    private function initialize(HTMLFilterConfiguration $config, $html_text)
    {
        $this->libxml_used_internal_errors = libxml_use_internal_errors(true);
        $this->config = $config;
        $this->original_dom = $this->createDOMDocument($html_text);
        $this->filterd_dom = $this->createDOMDocument("");
    }

    private function createDOMDocument($html_text)
    {
        $dom_document = new \DOMDocument("1.0", "UTF-8");
        $dom_document->loadHTML("<html><body>$html_text</body></html>");

        return $dom_document;
    }

    private function copyAllowedNodes()
    {
        $original_body = $this->findBodyNode($this->original_dom);
        $filterd_body = $this->findBodyNode($this->filterd_dom);
        $this->copyAllowedChildNodes($original_body, $filterd_body);
    }

    private function findBodyNode(\DOMDocument $dom_document)
    {
        return $dom_document->getElementsByTagName("body")->item(0);
    }

    private function copyAllowedChildNodes(
        \DOMNode $source,
        \DOMNode $destination
    ) {
        if ($source->childNodes === null) {
            return;
        }

        for ($i = 0, $l = $source->childNodes->length; $i != $l; ++$i) {
            $node = $source->childNodes->item($i);

            if ($this->isAllowedNode($node)) {
                $this->copyNode($node, $destination);
            } else {
                $this->copyAllowedChildNodes($node, $destination);
            }
        }
    }

    private function isAllowedNode(\DOMNode $node)
    {
        return ($node instanceof \DOMText)
            || ($node instanceof \DOMComment)
            || ($this->config->isAllowedTag($node->nodeName));
    }

    private function copyNode(\DOMNode $node, \DOMNode $destination)
    {
        if ($node instanceof \DOMText) {
            $this->copyTextNode($node, $destination);
        } else if ($node instanceof \DOMElement) {
            $this->copyDOMElement($node, $destination);
        } else if ($node instanceof \DOMComment) {
            $this->copyDOMComment($node, $destination);
        }
    }

    private function copyTextNode(\DOMText $text_node, \DOMNode $destination)
    {
        $destination->appendChild(
            $destination->ownerDocument->createTextNode($text_node->data)
        );
    }

    private function copyDOMElement(
        \DOMElement $element,
        \DOMNode $destination
    ) {
        $copied_element = $destination->ownerDocument
                                      ->createElement($element->nodeName);
        $destination->appendChild($copied_element);
        $this->copyAllowedAttributes($element, $copied_element);
        $this->copyAllowedChildNodes($element, $copied_element);
    }

    private function copyAllowedAttributes(
        \DOMElement $source,
        \DOMElement $destination
    ) {
        for ($i = 0, $l = $source->attributes->length; $i != $l; ++$i) {
            $attribute = $source->attributes->item($i);

            if ($this->isAllowedAttribute($attribute)) {
                $this->copyAttribute($attribute, $destination);
            }
        }
    }

    private function isAllowedAttribute(\DOMAttr $attribute)
    {
        return $this->config->isAllowedAttribute(
            $attribute->ownerElement->nodeName,
            $attribute->name,
            $attribute->value
        );
    }

    private function copyAttribute(
        \DOMAttr $attribute,
        \DOMElement $destination
    ) {
        $copied_attribute = $destination->ownerDocument
                                        ->createAttribute($attribute->name);
        $copied_attribute->value = htmlspecialchars(
            $attribute->value,
            ENT_QUOTES,
            "UTF-8"
        );
        $destination->appendChild($copied_attribute);
    }

    private function copyDOMComment(\DOMComment $comment, \DOMNode $destination)
    {
        $destination->appendChild(
            $destination->ownerDocument->createComment($comment->data)
        );
    }

    private function fetchFilteredHTML()
    {
        $filterd_html = $this->filterd_dom->saveXML(
            $this->findBodyNode($this->filterd_dom)
        );

        return $this->trimBodyTags($filterd_html);
    }

    private function trimBodyTags($html_text)
    {
        if ($html_text === "<body/>") {
            return "";
        }

        if (substr($html_text, 0, 6) === "<body>") {
            $html_text = substr($html_text, 6);
        }

        if (substr($html_text, -7, 7) === "</body>") {
            $html_text = substr($html_text, 0, strlen($html_text) - 7);
        }

        return $html_text;
    }

    private function cleanup()
    {
        $this->config = null;
        $this->original_dom = null;
        $this->filterd_dom = null;
        libxml_use_internal_errors($this->libxml_used_internal_errors);
    }
}