<?php

namespace Statamic\Fieldtypes;

use Statamic\Fields\Fields;
use Statamic\Fields\Fieldtype;
use Statamic\Query\Scopes\Filters\Fields\Replicator as ReplicatorFilter;
use Statamic\Support\Arr;

class Replicator extends Fieldtype
{
    protected $defaultValue = [];

    protected function configFieldItems(): array
    {
        return [
            'collapse' => [
                'display' => __('Collapse'),
                'instructions' => __('statamic::fieldtypes.replicator.config.collapse'),
                'type' => 'select',
                'cast_booleans' => true,
                'width' => 50,
                'options' => [
                    'false' => __('statamic::fieldtypes.replicator.config.collapse.disabled'),
                    'true' => __('statamic::fieldtypes.replicator.config.collapse.enabled'),
                    'accordion' => __('statamic::fieldtypes.replicator.config.collapse.accordion'),
                ],
                'default' => false,
            ],
            'max_sets' => [
                'display' => __('Max Sets'),
                'instructions' => __('statamic::fieldtypes.replicator.config.max_sets'),
                'type' => 'integer',
                'width' => 50,
            ],
            'sets' => [
                'type' => 'sets',
            ],
        ];
    }

    public function filter()
    {
        return new ReplicatorFilter($this);
    }

    public function process($data)
    {
        return collect($data)->map(function ($row) {
            return $this->processRow($row);
        })->all();
    }

    protected function processRow($row)
    {
        $row = array_except($row, '_id');

        $fields = $this->fields($row['type'])->addValues($row)->process()->values()->all();

        $row = array_merge($row, $fields);

        return Arr::removeNullValues($row);
    }

    public function preProcess($data)
    {
        return collect($data)->map(function ($row, $i) {
            return $this->preProcessRow($row, $i);
        })->all();
    }

    protected function preProcessRow($row, $index)
    {
        $fields = $this->fields($row['type'])->addValues($row)->preProcess()->values()->all();

        return array_merge($row, $fields, [
            '_id' => "set-$index",
            'enabled' => $row['enabled'] ?? true,
        ]);
    }

    public function fields($set)
    {
        return new Fields($this->config("sets.$set.fields"), $this->field()->parent());
    }

    public function extraRules(): array
    {
        return collect($this->field->value())->map(function ($set, $index) {
            return $this->setRules($set['type'], $set, $index);
        })->reduce(function ($carry, $rules) {
            return $carry->merge($rules);
        }, collect())->all();
    }

    protected function setRules($handle, $data, $index)
    {
        $rules = $this->fields($handle)->addValues($data)->validator()->rules();

        return collect($rules)->mapWithKeys(function ($rules, $handle) use ($index) {
            $rules = collect($rules)->map(function ($rule) use ($index) {
                return str_replace('{this}', $this->setRuleFieldPrefix($index), $rule);
            })->all();

            return [$this->setRuleFieldPrefix($index).'.'.$handle => $rules];
        })->all();
    }

    protected function setRuleFieldPrefix($index)
    {
        return "{$this->field->handle()}.{$index}";
    }

    public function extraValidationAttributes(): array
    {
        return collect($this->field->value())->map(function ($set, $index) {
            return $this->setValidationAttributes($set['type'], $set, $index);
        })->reduce(function ($carry, $rules) {
            return $carry->merge($rules);
        }, collect())->all();
    }

    protected function setValidationAttributes($handle, $data, $index)
    {
        $attributes = $this->fields($handle)->validator()->attributes();

        return collect($attributes)->mapWithKeys(function ($attribute, $handle) use ($index) {
            $attribute = str_replace('{this}', "{$this->field->handle()}.$index", $attribute);

            return [$this->setRuleFieldPrefix($index).'.'.$handle => $attribute];
        })->all();
    }

    protected function setConfig($handle)
    {
        return array_get($this->getFieldConfig('sets'), $handle);
    }

    public function augment($values)
    {
        return $this->performAugmentation($values, false);
    }

    public function shallowAugment($values)
    {
        return $this->performAugmentation($values, true);
    }

    protected function performAugmentation($values, $shallow)
    {
        return collect($values)->reject(function ($set, $key) {
            return array_get($set, 'enabled', true) === false;
        })->map(function ($set) use ($shallow) {
            if (! $this->config("sets.{$set['type']}.fields")) {
                return $set;
            }

            $augmentMethod = $shallow ? 'shallowAugment' : 'augment';

            $values = $this->fields($set['type'])->addValues($set)->{$augmentMethod}()->values();

            return $values->merge(['type' => $set['type']])->all();
        })->values()->all();
    }

    public function preload()
    {
        return [
            'existing' => $existing = collect($this->field->value())->mapWithKeys(function ($set) {
                $config = $this->config("sets.{$set['type']}.fields", []);

                return [$set['_id'] => (new Fields($config))->addValues($set)->meta()->put('_', '_')];
            })->toArray(),
            'new' => collect($this->config('sets'))->map(function ($set, $handle) {
                return (new Fields($set['fields']))->meta()->put('_', '_');
            })->toArray(),
            'defaults' => collect($this->config('sets'))->map(function ($set) {
                return (new Fields($set['fields']))->all()->map->defaultValue();
            })->all(),
            'collapsed' => [],
            'previews' => collect($existing)->map(function ($fields) {
                return collect($fields)->map(function () {
                    return null;
                })->all();
            })->all(),
        ];
    }
}
