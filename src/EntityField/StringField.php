<?php

namespace A2Global\CRMBundle\EntityField;

class StringField extends AbstractField
{
    public function getName(): string
    {
        return 'String';
    }

    public function getMySQLFieldType(): string
    {
        return 'VARCHAR(255)';
    }
}