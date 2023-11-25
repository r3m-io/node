<?php

namespace Package\R3m_io\Node\Controller;

use R3m\Io\App;


use R3m\Io\Module\Controller;
use R3m\Io\Module\Response;
use R3m\Io\Node\Model\Node as Model;

use Exception;

use R3m\Io\Exception\ObjectException;
use R3m\Io\Exception\FileWriteException;

class Node extends Controller {
    const DIR = __DIR__ . '/';

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function list(App $object){
        $model = new Model($object);

        $sort = $object->request('sort');
        if(empty($sort)){
            $sort = [
                'uuid' => 'ASC'
            ];
        }
        $filter = $object->request('filter');
        if(empty($filter)){
            $filter = [];
        }
        elseif(!is_array($filter)){
            throw new Exception('Filter must be an array.');
        }
        $response = $model->list(
            $object->request('class'),
            $model->role_system(), //leak
            [
                'sort' => $sort,
                'filter' => $filter,
                'limit' => (int) $object->request('limit'),
                'page' => (int) $object->request('page')
            ]
        );
        return new Response(
            $response,
            Response::TYPE_JSON
        );
    }

}