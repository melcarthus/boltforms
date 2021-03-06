<?php

namespace Bolt\Extension\Bolt\BoltForms\Config;

use Bolt\Extension\Bolt\BoltForms\Choice;
use Bolt\Extension\Bolt\BoltForms\Exception;
use Bolt\Extension\Bolt\BoltForms\Factory;
use Bolt\Storage\EntityManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Choices options for BoltForms
 *
 * Copyright (c) 2014-2016 Gawain Lynch
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Gawain Lynch <gawain.lynch@gmail.com>
 * @copyright Copyright (c) 2014-2016, Gawain Lynch
 * @license   http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 */
class FieldOptions
{
    /** @var string */
    private $formName;
    /** @var string */
    private $fieldName;
    /** @var string */
    private $type;
    /** @var array */
    private $baseOptions;
    /** @var array */
    private $options;
    /** @var EntityManager */
    private $em;
    /** @var boolean */
    private $initialised;
    /** @var EventDispatcherInterface */
    private $dispatcher;

    /**
     * Constructor.
     *
     * @param string                   $formName
     * @param string                   $fieldName
     * @param string                   $type
     * @param array                    $baseOptions
     * @param EntityManager            $storage
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(
        $formName,
        $fieldName,
        $type,
        array $baseOptions,
        EntityManager $storage,
        EventDispatcherInterface $dispatcher
    ) {
        $this->formName = $formName;
        $this->fieldName = $fieldName;
        $this->type = $type;
        $this->baseOptions = $baseOptions;
        $this->em = $storage;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Get the options array suitable for passing to Symfony Forms.
     *
     * @return array
     */
    public function toArray()
    {
        $this->initialise();

        return (array) $this->options;
    }

    /**
     * Build the options.
     */
    protected function initialise()
    {
        if ($this->initialised) {
            return;
        }

        $this->setValidOptions();
        $this->initialised = true;
    }

    /**
     * Get a clean array of options to be passed to Symfony Forms.
     *
     * @return array
     */
    protected function setValidOptions()
    {
        $this->options = isset($this->baseOptions) ? $this->baseOptions : null;
        if ($this->type === 'choice') {
            $this->resolveChoiceOptions();
        }

        // Set up constraint objects
        $this->setConstraints();
    }

    /**
     * Get customised value parameters for choice field types.
     *
     * @throws Exception\FormOptionException
     */
    protected function resolveChoiceOptions()
    {
        $options = $this->baseOptions;
        $choices = isset($options['choices']) ? $options['choices'] : null;

        if (is_string($choices)) {
            $choiceObj = $this->handleCustomChoice($choices);
        } else {
            $choiceObj = new Choice\ChoiceResolver($this->formName, $this->fieldName, $this->baseOptions);
        }

        $options = [
            'choices'           => $choiceObj->getChoices(),
            'choices_as_values' => $choiceObj->isChoicesAsValues(),
            'choice_loader'     => $choiceObj->getChoiceLoader(),
            'choice_name'       => $choiceObj->getChoiceName(),
            'choice_value'      => $choiceObj->getChoiceValue(),
            'choice_label'      => $choiceObj->getChoiceLabel(),
            'choice_attr'       => $choiceObj->getChoiceAttr(),
            'group_by'          => $choiceObj->getGroupBy(),
            'preferred_choices' => $choiceObj->getPreferredChoices(),
        ];

        $this->options = array_merge($this->baseOptions, $options);

        unset($this->options['params']);
    }

    /**
     * @param $choices
     *
     * @return Choice\AbstractChoiceOptionResolver
     */
    protected function handleCustomChoice($choices)
    {
        // Check if it is one of our custom types
        if (strpos($choices, 'contenttype::') === 0) {
            // @deprecated Will be remove in BoltForms v4
            return new Choice\ContentTypeResolver($this->formName, $this->fieldName, $this->baseOptions, $this->em, true);
        }

        if (strpos($choices, 'content') === 0) {
            return new Choice\ContentTypeResolver($this->formName, $this->fieldName, $this->baseOptions, $this->em);
        }

        if (strpos($choices, 'event') === 0) {
            return new Choice\EventResolver($this->formName, $this->fieldName, $this->baseOptions, $this->dispatcher);
        }

        return new Choice\ChoiceResolver($this->formName, $this->fieldName, $this->baseOptions);
    }

    /**
     * Set the constraints classes properly.
     */
    protected function setConstraints()
    {
        if (!isset($this->baseOptions['constraints'])) {
            return;
        }

        if (gettype($this->baseOptions['constraints']) === 'string') {
            $this->options['constraints'] = Factory\FieldConstraint::get($this->formName, $this->baseOptions['constraints']);

            return;
        }

        foreach ($this->baseOptions['constraints'] as $key => $constraint) {
            $this->options['constraints'][$key] = Factory\FieldConstraint::get($this->formName, [$key => $constraint]);
        }
    }
}
