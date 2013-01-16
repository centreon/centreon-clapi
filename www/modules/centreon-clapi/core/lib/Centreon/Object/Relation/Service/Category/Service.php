<?php

require_once "Centreon/Object/Relation/Relation.php";

class Centreon_Object_Relation_Service_Category_Service extends Centreon_Object_Relation
{
    protected $relationTable = "service_categories_relation";
    protected $firstKey = "sc_id";
    protected $secondKey = "service_service_id";
}