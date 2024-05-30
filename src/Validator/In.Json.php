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

use R3m\Io\Module\Data;
use R3m\Io\Module\Filter;

use R3m\Io\Node\Model\Node;

use R3m\Io\Exception\ObjectException;
use R3m\Io\Exception\FileWriteException;

/**
 * @throws ObjectException
 * @throws FileWriteException
 * @throws Exception
 */
function validate_in_json(App $object, $request=null, $field='', $argument='', $function=false): bool
{
    $url = $argument->url ?? false;
    $list = $argument->list ?? false;
    $filter = $argument->filter ?? null;
    $where = $argument->where ?? null;
    $key = $argument->key ?? false;
    $inverse = $argument->inverse ?? false;
    $type = $argument->type ?? 'auto';
    $data = $argument->data ?? null;
    if($filter === null && $where === null){
        return false;
    }
    if($data === null){
        if($url === false) {
            return false;
        }
        if(!File::exist($url)){
            return false;
        }
        $data = $object->parse_read($url, sha1($url));
    } else {
        $data = new Data($data);
    }
    if($data){
        if($where){
            if($key) {
                $data_key = $data->data($key);
                d($key);
                ddd($data_key);
            } else {
                $data_key = $data->data();
                if(
                    $data_key !==null &&
                    !is_scalar($data_key)
                ) {
                    if ($type === Filter::TYPE_AUTO) {
                        $type = Filter::is_type($data_key);
                    }
                    switch ($type) {
                        case 'list':
                            ddd('list');
                            $node = new Node();
                            $data_filter = Filter::list($data_key)->where($filter);
                            break;
                        case 'record':
                            ddd('record');
                            $node = new Node();
                            $data_filter = Filter::record($data_key)->where($filter);
                            break;
                        default:
                            throw new Exception('Type (' . $type . ') not supported in ' . __FUNCTION__ . ', supported types: list, record');
                    }
                }
            }
        }
        elseif($filter){
            if($key) {
                $data_key = $data->data($key);
                if (
                    $data_key !==null &&
                    !is_scalar($data_key)
                ) {
                    if($type === Filter::TYPE_AUTO){
                        $type = Filter::is_type($data_key);
                    }
                    switch($type){
                        case 'list':
                            $data_filter = Filter::list($data_key)->where($filter);
                            break;
                        case 'record':
                            $data_filter = Filter::record($data_key)->where($filter);
                            break;
                        default:
                            throw new Exception('Type (' . $type . ') not supported in ' . __FUNCTION__ . ', supported types: list, record');
                    }
                    if(!empty($data_filter)){
                        return !$inverse;
                    }
                } else {
                    throw new Exception('Key (' . $key . ') is scalar in ' . __FUNCTION__ . ', expected array, object');
                }
            } else {
                $data_key = $data->data();
                if(
                    $data_key !==null &&
                    !is_scalar($data_key)
                ){
                    if($type === Filter::TYPE_AUTO){
                        $type = Filter::is_type($data_key);
                    }
                    switch($type){
                        case 'list':
                            $data_filter = Filter::list($data_key)->where($filter);
                            break;
                        case 'record':
                            $data_filter = Filter::record($data_key)->where($filter);
                            break;
                        default:
                            throw new Exception('Type (' . $type . ') not supported in ' . __FUNCTION__ . ', supported types: list, record');
                    }
                    if(!empty($data_filter)){
                        return !$inverse;
                    }
                } else {
                    throw new Exception('Key (' . $key . ') is scalar in ' . __FUNCTION__ . ', expected array, object');
                }
            }
        }
    }
    return $inverse;
}