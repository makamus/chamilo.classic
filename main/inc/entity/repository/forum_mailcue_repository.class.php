<?php

namespace Entity\Repository;
use \db;

/**
 * 
 * @license see /license.txt
 * @author autogenerated
 */
class ForumMailcueRepository extends \EntityRepository
{

    /**
     * @return \Entity\Repository\ForumMailcueRepository
     */
    public static function instance(){
        static $result = false;
        if($result === false){
            $result = db::instance()->get_repository('\Entity\ForumMailcue');
        }
        return $result;
    }
    
    /**
     * 
     * @param EntityManager $em The EntityManager to use.
     * @param ClassMetadata $class The class descriptor.
     */
    public function __construct($em, $class){
        parent::__construct($em, $class);
    }
    
}