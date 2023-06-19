<?php

namespace JsonSchema\Constraints;

class FilterTagsAndAttributes
{
    private $allowedTags = [];
    private $allowedAttributes = [];

    public function __construct(array $allowTags, array $allowAttribs = [])
    {
        $this->setAllowedTags($allowTags);
        $this->setAttributesAllowed($allowAttribs);
    }

    private function setAllowedTags(array $allowedTags)
    {
        foreach ($allowedTags as $index => $element) {
            // If the tag was provided without attributes
            if (is_int($index) && is_string($element)) {
                // Canonicalize the tag name
                $tagName = strtolower($element);
                // Store the tag as allowed with no attributes
                $this->allowedTags[$tagName] = array();
            } // Otherwise, if a tag was provided with attributes
            else {
                if (is_string($index) && (is_array($element) || is_string($element))) {
                    // Canonicalize the tag name
                    $tagName = strtolower($index);
                    // Canonicalize the attributes
                    if (is_string($element)) {
                        $element = array($element);
                    }
                    // Store the tag as allowed with the provided attributes
                    $this->allowedTags[$tagName] = array();
                    foreach ($element as $attribute) {
                        if (is_string($attribute)) {
                            // Canonicalize the attribute name
                            $attributeName = strtolower($attribute);
                            $this->allowedTags[$tagName][$attributeName] = null;
                        }
                    }
                }
            }
        }
    }

    private function setAttributesAllowed(array $attributesAllowed)
    {
        // Store each attribute as allowed
        foreach ($attributesAllowed as $attribute) {
            if (is_string($attribute)) {
                // Canonicalize the attribute name
                $attributeName = strtolower($attribute);
                $this->allowedAttributes[$attributeName] = null;
            }
        }
    }

    public function filter(string $value): string
    {
        // Initialize accumulator for filtered data
        $dataFiltered = '';
        // Parse the input data iteratively as regular pre-tag text followed by a
        // tag; either may be empty strings
        preg_match_all('/([^<]*)(<?[^>]*>?)/', (string)$value, $matches);

        // Iterate over each set of matches
        foreach ($matches[1] as $index => $preTag) {
            // If the pre-tag text is non-empty, strip any ">" characters from it
            if (strlen($preTag)) {
                $preTag = str_replace('>', '', $preTag);
            }
            // If a tag exists in this match, then filter the tag
            $tag = $matches[2][$index];
            if (strlen($tag)) {
                $tagFiltered = $this->filterTag($tag);
            } else {
                $tagFiltered = '';
            }
            // Add the filtered pre-tag text and filtered tag to the data buffer
            $dataFiltered .= $preTag . $tagFiltered;
        }

        return $dataFiltered;
    }

    /**
     * Filters a single tag against the current option settings
     */
    private function filterTag(string $tag): string
    {
        // Parse the tag into:
        // 1. a starting delimiter (mandatory)
        // 2. a tag name (if available)
        // 3. a string of attributes (if available)
        // 4. an ending delimiter (if available)
        $isMatch = preg_match('~(</?)(\w*)((/(?!>)|[^/>])*)(/?>)~', $tag, $matches);

        // If the tag does not match, then strip the tag entirely
        if (!$isMatch) {
            return '';
        }

        // Save the matches to more meaningfully named variables
        $tagStart = $matches[1];
        $tagName = strtolower($matches[2]);
        $tagAttributes = $matches[3];
        $tagEnd = $matches[5];

        // If the tag is not an allowed tag, then remove the tag entirely
        if (!isset($this->allowedTags[$tagName])) {
            return '';
        }

        // Trim the attribute string of whitespace at the ends
        $tagAttributes = trim($tagAttributes);

        // If there are non-whitespace characters in the attribute string
        if (strlen($tagAttributes)) {
            // Parse iteratively for well-formed attributes
            preg_match_all('/([\w-]+)\s*=\s*(?:(")(.*?)"|(\')(.*?)\')/s', $tagAttributes, $matches);

            // Initialize valid attribute accumulator
            $tagAttributes = '';

            // Iterate over each matched attribute
            foreach ($matches[1] as $index => $attributeName) {
                $attributeName = strtolower($attributeName);
                $attributeDelimiter = empty($matches[2][$index]) ? $matches[4][$index] : $matches[2][$index];
                $attributeValue = empty($matches[3][$index]) ? $matches[5][$index] : $matches[3][$index];

                $dataAttributeAllowed = (strpos($attributeName, 'data-') === 0 && array_key_exists(
                        'data-*',
                        $this->allowedAttributes
                    ));

                // If the attribute is not allowed, then remove it entirely
                if (!array_key_exists($attributeName, $this->allowedTags[$tagName])
                    && !array_key_exists($attributeName, $this->allowedAttributes) && !$dataAttributeAllowed) {
                    continue;
                }
                // Add the attribute to the accumulator
                $tagAttributes .= " $attributeName=" . $attributeDelimiter
                    . $attributeValue . $attributeDelimiter;
            }
        }

        // Reconstruct tags ending with "/>" as backwards-compatible XHTML tag
        if (strpos($tagEnd, '/') !== false) {
            $tagEnd = " $tagEnd";
        }

        // Return the filtered tag
        return $tagStart . $tagName . $tagAttributes . $tagEnd;
    }
}