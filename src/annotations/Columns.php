<?php
namespace entitys\annotations;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Columns
{
    public function __construct(public bool $primary = false, public bool $unique = false,public bool $ai = false, public string $name = "") {
    }    
}
