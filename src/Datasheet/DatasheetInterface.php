<?php

namespace A2Global\CRMBundle\Datasheet;

interface DatasheetInterface
{
    public function getItems(int $startFrom = 0, int $limit = 0, $sort = [], $filters = []);

    public function getItemsTotal();
}