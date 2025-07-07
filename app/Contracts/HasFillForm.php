<?php 

namespace App\Contracts;

interface HasFillForm
{
    public static function getFillForm(mixed $context): array;
}