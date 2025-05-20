<?php
namespace entitys\annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(public string $name = "") {

    }
}
