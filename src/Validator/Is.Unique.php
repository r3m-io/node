<?php

/**
 * @author          Remco van der Velde
 * @since           2020-09-18
 * @copyright       Remco van der Velde
 * @license         MIT
 * @version         1.0
 * @changeLog
 *     -            all
 */


use R3m\Io\App;

use R3m\Io\Module\Controller;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Filter;
use R3m\Io\Module\Data as Storage;
use R3m\Io\Module\Parse;

use R3m\Io\Node\Model\Node;

/**
 * @throws Exception
 */
function validate_is_unique(App $object, $value='', $attribute='', $validate='')
{
    $dir_node = $object->config('project.dir.node');
    $url = false;
    $name = false;
    if (is_object($validate)) {
        if (property_exists($validate, 'class')) {
            $name = Controller::name($validate->class);
            $url = $dir_node . 'Data' . $object->config('ds') . $name . $object->config('extension.json');
        }
        if (property_exists($validate, 'attribute')) {
            $attribute = $validate->attribute;
            if (is_array($attribute)) {
                $value = [];
                foreach ($attribute as $nr => $record) {
                    $explode = explode(':', $record);
                    foreach($explode as $explode_nr => $explode_value){
                        $explode[$explode_nr] = trim($explode_value);
                    }
                    $value[$nr] = $object->request('node.' . trim($explode[0]));
                    if(
                        $value[$nr] === null ||
                        $value[$nr] === ''
                    ){
                        throw new Exception('Is.Unique: ' . $explode[0] . ' is empty');
                    }
                }
            }
        }
    }
    if (
        is_array($attribute) &&
        is_array($value)
    ) {
        $options = [
            'filter' => [],
            'sort' => []
        ];
        foreach ($attribute as $nr => $record) {
            if (array_key_exists($nr, $value)) {
                $explode = explode(':', $record);
                foreach($explode as $explode_nr => $explode_value){
                    $explode[$explode_nr] = trim($explode_value);
                }
                if(array_key_exists(1, $explode)){
                    $options['filter'][$explode[1]]['operator'] = Filter::OPERATOR_STRICTLY_EXACT;
                    $options['filter'][$explode[1]]['value'] = $value[$nr];
                    $options['sort'][$explode[1]] = 'ASC';
                } else {
                    $options['filter'][$explode[0]]['operator'] = Filter::OPERATOR_STRICTLY_EXACT;
                    $options['filter'][$explode[0]]['value'] = $value[$nr];
                    $options['sort'][$explode[0]] = 'ASC';
                }
            }
        }
    } else {
        $options = [
            'filter' => [
                $attribute => [
                    'operator' => Filter::OPERATOR_STRICTLY_EXACT,
                    'value' => $value
                ]
            ],
            'sort' => [
                $attribute => 'ASC'
            ]
        ];
    }
    $node = new Node($object);
    $response = $node->record($name, $node->role_system(), $options);
    if(array_key_exists('node', $response)){
        $record = $response['node'];
        if(
            is_object($record) &&
            property_exists($record, 'uuid') &&
            !empty($record->uuid)
        ){
            return false;
        } else {
            return true;
        }
    } else {
        return true;
    }
}
