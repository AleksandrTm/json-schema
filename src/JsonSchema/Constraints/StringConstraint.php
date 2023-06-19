<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

use JsonSchema\Entity\JsonPointer;

/**
 * The StringConstraint Constraints, validates an string against a given schema
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class StringConstraint extends Constraint
{
    /**
     * {@inheritdoc}
     */
    public function check(&$value, $schema = null, JsonPointer $path = null, $i = null)
    {
        // Verify maxLength
        if (isset($schema->maxLength) && $this->strlen($value, $schema) > $schema->maxLength) {
            $this->addError($path, 'Must be at most ' . $schema->maxLength . ' characters long', 'maxLength', array(
                'maxLength' => $schema->maxLength,
            ));
        }

        //verify minLength
        if (isset($schema->minLength) && $this->strlen($value, $schema) < $schema->minLength) {
            $this->addError($path, 'Must be at least ' . $schema->minLength . ' characters long', 'minLength', array(
                'minLength' => $schema->minLength,
            ));
        }

        if (isset($schema->pregReplace) && is_array($schema->pregReplace) && count($schema->pregReplace) === 2) {
            $value = preg_replace($schema->pregReplace[0], $schema->pregReplace[1], $value);
        }

        // Verify a regex pattern
        if (isset($schema->pattern) && !preg_match('#' . str_replace('#', '\\#', $schema->pattern) . '#u', $value)) {
            $this->addError($path, 'Does not match the regex pattern ' . $schema->pattern, 'pattern', array(
                'pattern' => $schema->pattern,
            ));
        }

        $this->checkFormat($value, $schema, $path, $i);
    }

    private function strlen($string, $schema)
    {
        if (isset($schema->ignoreHtmlTagsLength) && $schema->ignoreHtmlTagsLength === true) {
            $string = strip_tags($string);
        }

        if (extension_loaded('mbstring')) {
            return mb_strlen($string, mb_detect_encoding($string));
        }

        // mbstring is present on all test platforms, so strlen() can be ignored for coverage
        return strlen($string); // @codeCoverageIgnore
    }
}
