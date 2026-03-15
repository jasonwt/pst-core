<?php

declare(strict_types=1);

namespace PST\Core;

interface IDisposable {
    public function dispose(): void;
}