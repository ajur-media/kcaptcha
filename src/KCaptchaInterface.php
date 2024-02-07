<?php

namespace AJUR\Template;

interface KCaptchaInterface
{
    public function __construct(array $config = []);

    public function display():void;

    public function getKeyString():string;

    public function &getImageResource();

}