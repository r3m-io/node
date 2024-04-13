<?php

namespace R3m\Io\Node\Trait;

use R3m\Io\Module\File;
use R3m\Io\Module\Filter;
use R3m\Io\Module\Parse\Token;


use Exception;

trait Where {

    private function operator($tree=[]): mixed
    {
        if(!is_array($tree)){
            return $tree;
        }
        $result = [];
        $previous = null;
        $is_collect = false;
        $operator = '';
        foreach($tree as $nr => $record){
            if(array_key_exists($nr - 1, $tree)){
                $previous = $nr - 1;
            }
            if(
                $is_collect === false &&
                array_key_exists('type', $record) &&
                array_key_exists('value', $record) &&
                $record['type'] === Token::TYPE_IS_MINUS &&
                $record['value'] === '-' &&
                array_key_exists($previous, $tree) &&
                array_key_exists('type', $tree[$previous]) &&
                $tree[$previous]['type'] !== Token::TYPE_WHITESPACE
            ) {
                if($previous >= 0){
                    $operator = $tree[$previous]['value'];
                }
                $operator .= $record['value'];
                $is_collect = $nr;
                if($previous >= 0){
                    $tree[$nr]['column'] = $tree[$previous]['column'];
                    $tree[$nr]['row'] = $tree[$previous]['row'];
                    unset($tree[$previous]);
                }
                continue;
            }
            if($is_collect !== false){
                if($record['type'] === Token::TYPE_WHITESPACE){
                    $tree[$is_collect]['value'] = $operator;
                    $tree[$is_collect]['type'] = $operator;
                    $tree[$is_collect]['is_operator'] = true;
                    $is_collect = false;
                    $operator = '';
                    continue;
                }
                $operator .= $record['value'];
                unset($tree[$nr]);
            }
        }
        if($is_collect){
            $tree[$is_collect]['value'] = $operator;
            $tree[$is_collect]['type'] = $operator;
            $tree[$is_collect]['is_operator'] = true;
            $is_collect = false;
        }
        return $tree;
    }

    /**
     * @throws Exception
     */
    private function where_convert($input=[]): array
    {
        if(is_array($input)){
            $is_string = true;
            foreach($input as $nr => $line){
                if(!is_string($line)){
                    $is_string = false;
                    break;
                }
            }
            if($is_string){
                $input = implode(' ', $input);
            }
        }
        $string = $input;
        if(!is_string($string)){
            return $string;
        }
        $options = [
            'with_whitespace' => true,
            'extra_operators' => [
                'and',
                'or',
                'xor'
            ]
        ];
        $tree = Token::tree('{' . $string . '}', $options);
        $tree = $this->operator($tree);
        $is_collect = false;
        $previous = null;
        $next = null;
        foreach($tree as $nr => $record){
            if(array_key_exists($nr - 1, $tree)){
                $previous = $nr - 1;
            }
            if(array_key_exists($nr - 2, $tree)){
                $next = $nr - 2;
            }
            if($record['type'] === Token::TYPE_CURLY_OPEN){
                unset($tree[$nr]);
            }
            elseif($record['type'] === Token::TYPE_CURLY_CLOSE){
                unset($tree[$nr]);
            }
            elseif($record['type'] === Token::TYPE_WHITESPACE){
                if(!empty($collection)){
                    if(array_key_exists($is_collect, $tree)){
                        $tree[$is_collect]['collection'] = $collection;
                        $tree[$is_collect]['type'] = Token::TYPE_COLLECTION;
                        $tree[$is_collect]['value'] = '';
                    }
                    $collection = [];
                }
                $is_collect = false;
                unset($tree[$nr]);
            }
            elseif($record['value'] === '('){
                $tree[$nr] = '(';
            }
            elseif($record['value'] === ')'){
                $tree[$nr] = ')';
            }
            elseif($is_collect === false && $record['value'] === '.'){
                $is_collect = true;
                $collection = [];
                $collection[] = $tree[$previous];
                unset($tree[$previous]);
            }
            elseif(
                in_array(
                    strtolower($record['value']),
                    [
                        'and',
                        'or',
                        'xor'
                    ],
                    true
                )
            ){
                $tree[$nr] = $record['value'];
            }
            if($is_collect === true){
                $collection[] = $record;
                $is_collect = $nr;
            }
            elseif($is_collect){
                if($record['type'] !== Token::TYPE_CURLY_CLOSE){
                    $collection[] = $record;
                }
                unset($tree[$nr]);
            }
        }
        if(!empty($collection)){
            if(array_key_exists($is_collect, $tree)){
                $tree[$is_collect]['collection'] = $collection;
                $tree[$is_collect]['type'] = Token::TYPE_COLLECTION;
                $tree[$is_collect]['value'] = '';
            }
            $collection = [];
        }
        $previous = null;
        $next = null;
        $list = [];
        foreach($tree as $nr => $record){
            $list[] = $record;
            unset($tree[$nr]);
        }
        foreach($list as $nr => $record){
            if(array_key_exists($nr - 1, $list)){
                $previous = $nr - 1;
            }
            if(array_key_exists($nr + 1, $list)){
                $next = $nr + 1;
            }
            if(!is_array($record)){
                continue;
            }
            if(
                array_key_exists('is_operator', $record) &&
                $record['is_operator'] === true
            ){
                $attribute = $this->tree_record_attribute($list[$previous]);
                $operator = $record['value'];
                $value = $this->tree_record_attribute($list[$next]);

                $list[$previous] = [
                    'attribute' => $attribute,
                    'value' => $value,
                    'operator' => $operator
                ];
                unset($list[$nr]);
                unset($list[$next]);
            }
            elseif(
                in_array(
                    strtolower($record['value']),
                    Filter::OPERATOR_LIST_NAME,
                    true
                )
            ){
                $attribute = $this->tree_record_attribute($list[$previous]);
                $operator = strtolower($record['value']);
                $value = $this->tree_record_attribute($list[$next]);
                $list[$previous] = [
                    'attribute' => $attribute,
                    'value' => $value,
                    'operator' => $operator
                ];
                unset($list[$nr]);
                unset($list[$next]);
            }
        }
        $tree = [];
        foreach($list as $nr => $record){
            $tree[] = $record;
            unset($list[$nr]);
        }
        return $tree;
    }

    private function where_get_depth($where=[]): int
    {
        $depth = 0;
        $deepest = 0;
        if(!is_array($where)){
            return $depth;
        }
        foreach($where as $key => $value){
            if($value === '('){
                $depth++;
            }
            if($value === ')'){
                $depth--;
            }
            if($depth > $deepest){
                $deepest = $depth;
            }
        }
        return $deepest;
    }

    private function where_get_set(&$where=[], &$key=null, $deep=0): array
    {
        $set = [];
        $depth = 0;
        //convert where to array.
        if(!is_array($where)){
            return $set;
        }
        foreach($where as $nr => $value){
            if($value === '('){
                $depth++;
            }
            if($value === ')'){
                if($depth === $deep){
                    unset($where[$nr]);
                    if(!empty($set)){
                        break;
                    }
                }
                $depth--;
                if(
                    $depth === $deep &&
                    !empty($set)
                ){
                    break;
                }
            }
            if($depth === $deep){
                if($key === null){
                    $key = $nr;
                }
                if(!in_array($value, [
                    '(',
                    ')'
                ], true)) {
                    $set[] = $value;
                }
                unset($where[$nr]);
            }
        }
        return $set;
    }

    /**
     * @throws Exception
     */
    private function where_process($record=[], $set=[], &$where=[], &$key=null, &$operator=null, $options=[]): ?array
    {
        $count = count($set);
        if(
            array_key_exists(0, $set) &&
            $count === 1
        ){
            $operator = null;
            if($set[0] === true || $set[0] === false){
                $where[$key] = $set[0];
                return $set;
            }
            $list = [];
            $list[] = $record;
            if(
                array_key_exists('attribute', $set[0]) &&
                array_key_exists('value', $set[0]) &&
                array_key_exists('operator', $set[0])
            ){
                $filter_where = [
                    $set[0]['attribute'] => [
                        'value' => $set[0]['value'],
                        'operator' => $set[0]['operator']
                    ]
                ];
                /*
                if(array_key_exists('index', $options)){
                    $object = $this->object();
                    $ramdisk_dir_node = $object->config('ramdisk.url') .
                        $object->config('posix.id') .
                        $object->config('ds') .
                        'Node' .
                        $object->config('ds')
                    ;
                    $ramdisk_dir_index = $ramdisk_dir_node .
                        'Index' .
                        $object->config('ds')
                    ;
                    if(
                        array_key_exists('unique', $options['index']) &&
                        $options['index']['unique'] === true
                    ){
                        $is_unique = 'unique';
                    } else {
                        $is_unique = '';
                    }
                    $url = $ramdisk_dir_index .
                        ($options['index']['chunk_nr'] + 1) .
                        '-' .
                        $options['index']['threads'] .
                        '-' .
                        $set[0]['attribute'] .
                        '-' .
                        $is_unique .
                        $object->config('extension.json')
                    ;
                    if(
                        File::exist($url) &&
                        File::mtime($url) === $options['index']['mtime']
                    ){
                        //we have a cached index
                        $read = $object->data_read($url, sha1($url));
                        if(
                            $read &&
                            in_array(
                                $set[0]['operator'],
                                [
                                    '===',
                                    '==',
                                    '!==',
                                    '!=',
                                    'strictly-exact',
                                    'exact',
                                    'not-strictly-exact',
                                    'not-exact',
                                ],
                                true
                            )
                        ){
                            $index = $read->data($set[0]['value']);
                            if($index === $options['index']['iterator']){
                                $where[$key] = true;
                                $set[0] = true;
                            } else {
                                $where[$key] = false;
                                $set[0] = false;
                            }
                            return $set;
                        }
                        elseif(
                            $read &&
                            in_array(
                                $set[0]['operator'],
                                [
                                    'partial',
                                    'not-partial'
                                ],
                                true
                            )
                        ){
                            foreach($read->data() as $read_nr => $read_value){
                                if($set[0]['operator'] === 'partial'){
                                    if(strpos($read_value, $set[0]['value']) !== false){
                                        $index = $read->data($set[0]['value']);
                                        if($index === $options['index']['iterator']){
                                            $where[$key] = true;
                                            $set[0] = true;
                                        } else {
                                            $where[$key] = false;
                                            $set[0] = false;
                                        }
                                    } else {
                                        $where[$key] = false;
                                        $set[0] = false;
                                    }
                                    return $set;
                                }
                                elseif($set[0]['operator'] === 'not-partial'){
                                    if(strpos($read_value, $set[0]['value']) === false){
                                        $index = $read->data($set[0]['value']);
                                        if($index === $options['index']['iterator']){
                                            $where[$key] = true;
                                            $set[0] = true;
                                        } else {
                                            $where[$key] = false;
                                            $set[0] = false;
                                        }
                                    } else {
                                        $where[$key] = false;
                                        $set[0] = false;
                                    }
                                    return $set;
                                }
                            }
                        }
                    }
                }
                */
                $left = Filter::list($list)->where($filter_where);
                if(!empty($left)){
                    $where[$key] = true;
                    $set[0] = true;
                } else {
                    $where[$key] = false;
                    $set[0] = false;
                }
            }
            return $set;
        }
        elseif(
            array_key_exists(0, $set) &&
            array_key_exists(1, $set) &&
            array_key_exists(2, $set)
        ){
            switch($set[1]) {
                case 'or':
                    $operator = 'or';
                    if ($set[0] === true || $set[2] === true) {
                        $where[$key] = true;
                        return $set;
                    }
                    $list = [];
                    $list[] = $record;
                    if ($set[0] === false) {
                        $left = $set[0];
                    } elseif (
                        is_array($set[0]) &&
                        array_key_exists('attribute', $set[0]) &&
                        array_key_exists('value', $set[0]) &&
                        array_key_exists('operator', $set[0])
                    ) {
                        $filter_where = [
                            $set[0]['attribute'] => [
                                'value' => $set[0]['value'],
                                'operator' => $set[0]['operator']
                            ]
                        ];
                        $left = Filter::list($list)->where($filter_where);
                    }
                    if ($set[2] === false) {
                        $right = $set[2];
                    } elseif (
                        is_array($set[2]) &&
                        array_key_exists('attribute', $set[2]) &&
                        array_key_exists('value', $set[2]) &&
                        array_key_exists('operator', $set[2])
                    ) {
                        $filter_where = [
                            $set[2]['attribute'] => [
                                'value' => $set[2]['value'],
                                'operator' => $set[2]['operator']
                            ]
                        ];
                        $right = Filter::list($list)->where($filter_where);
                    }
                    if (!empty($left)) {
                        $where[$key] = true;
                        $set[0] = true;
                    } else {
                        $set[0] = false;
                    }
                    if (!empty($right)) {
                        $where[$key] = true;
                        $set[2] = true;
                    } else {
                        $set[2] = false;
                    }
                    if (!empty($left) || !empty($right)) {
                        //nothing
                    } else {
                        $where[$key] = false;
                    }
                    return $set;
                case 'and':
                    $operator = 'and';
                    if ($set[0] === true && $set[2] === true) {
                        $where[$key] = true;
                        return $set;
                    }
                    elseif ($set[0] === false && $set[2] === false) {
                        $where[$key] = false;
                        return $set;
                    }
                    elseif($set[0] === false){
                        $where[$key] = false;
                        $set[0] = false;
                        $set[2] = false;
                        return $set;
                    }
                    $list = [];
                    $list[] = $record;
                    if (
                        is_array($set[0]) &&
                        is_array($set[2]) &&
                        array_key_exists('attribute', $set[0]) &&
                        array_key_exists('value', $set[0]) &&
                        array_key_exists('operator', $set[0]) &&
                        array_key_exists('attribute', $set[2]) &&
                        array_key_exists('value', $set[2]) &&
                        array_key_exists('operator', $set[2])
                    ) {
                        $filter_where = [
                            $set[0]['attribute'] => [
                                'value' => $set[0]['value'],
                                'operator' => $set[0]['operator']
                            ],
                            $set[2]['attribute'] => [
                                'value' => $set[2]['value'],
                                'operator' => $set[2]['operator']
                            ]
                        ];
                        $and = Filter::list($list)->where($filter_where);
                        if (!empty($and)) {
                            $where[$key] = true;
                            $set[0] = true;
                            $set[2] = true;
                        } else {
                            $where[$key] = false;
                            $set[0] = false;
                            $set[2] = false;
                        }
                        return $set;
                    }
                    /**
                     * more than "1 'and' or 'or'"
                     */
                    elseif(
                        $set[0] === true &&
                        is_array($set[2]) &&
                        array_key_exists('attribute', $set[2]) &&
                        array_key_exists('value', $set[2]) &&
                        array_key_exists('operator', $set[2])
                    ) {
                        $filter_where = [
                            $set[2]['attribute'] => [
                                'value' => $set[2]['value'],
                                'operator' => $set[2]['operator']
                            ]
                        ];
                        $and = Filter::list($list)->where($filter_where);
                        if (!empty($and)) {
                            $where[$key] = true;
                            $set[0] = true;
                            $set[2] = true;
                        } else {
                            $where[$key] = false;
                            $set[0] = false;
                            $set[2] = false;
                        }
                        return $set;
                    }
                case 'xor' :
                    $operator = 'xor';
                    $list = [];
                    $list[] = $record;
                    $is_true = 0;
                    $left = null;
                    foreach ($set as $nr => $true) {
                        if(
                            in_array($true, [
                                'and',
                                'or'
                            ], true)
                        ){
                            throw new Exception('And or Or not allowed in Xor, use sets instead.');
                        }
                        elseif (
                            $true === true
                        ) {
                            $is_true++;
                        }
                        elseif (
                            is_array($true) &&
                            array_key_exists('attribute', $true) &&
                            array_key_exists('value', $true) &&
                            array_key_exists('operator', $true)
                        ) {
                            $filter_where = [
                                $true['attribute'] => [
                                    'value' => $true['value'],
                                    'operator' => $true['operator']
                                ]
                            ];
                            $current = Filter::list($list)->where($filter_where);
                            if (!empty($current)) {
                                $is_true++;
                                $set[$nr] = true;
                            } else {
                                $set[$nr] = false;
                            }
                        }
                    }
                    if ($is_true === 1) {
                        $where[$key] = true;
                        $set = [];
                        $set[0] = true;
                        $operator = null;
                        return $set;
                    }
                    $where[$key] = false;
                    $set = [];
                    $set[0] = false;
                    return $set;


            }
        }
        return null;
    }

    /**
     * @throws Exception
     */
    private function where($record=[], $where=[], $options=[]): false | array | object
    {
        if(empty($where)){
            return $record;
        }
        $deepest = $this->where_get_depth($where);
        $counter =0;
        while($deepest >= 0){
            if($counter > 1024){
                break;
            }
            $set = $this->where_get_set($where, $key, $deepest);
            while($record !== false){
                $set = $this->where_process($record, $set, $where, $key, $operator, $options);
                if(empty($set) && $deepest === 0){
                    return $record;
                }
                $count_set = count($set);
                if($count_set === 1){
                    if($operator === null && $set[0] === true){
                        break;
                    } else {
                        if($deepest === 0){
                            $record = false;
                            break 2;
                        } else {
                            break;
                        }
                    }
                }
                elseif($count_set >= 3){
                    switch($operator){
                        case 'and':
                            if($set[0] === true && $set[2] === true){
                                array_shift($set);
                                array_shift($set);
                                $set[0] = true;
                            } else {
                                array_shift($set);
                                array_shift($set);
                                $set[0] = false;
                            }
                            break;
                        case 'or':
                            if($set[0] === true || $set[2] === true){
                                array_shift($set);
                                array_shift($set);
                                $set[0] = true;
                            } else {
                                array_shift($set);
                                array_shift($set);
                                $set[0] = false;
                            }
                            break;
                        default:
                            throw new Exception('Unknown operator: ' . $operator);
                    }
                }
                $counter++;
                if($counter > 1024){
                    break 2;
                }
            }
            if($record === false){
                break;
            }
            if($deepest === 0){
                break;
            }
            ksort($where, SORT_NATURAL);
            $deepest = $this->where_get_depth($where);
            unset($key);
            $counter++;
        }
        return $record;
    }
}