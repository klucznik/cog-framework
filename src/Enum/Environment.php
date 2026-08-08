<?php

namespace Cog\Enum;

enum Environment: string {
	case DEV = 'development';
	case TEST = 'testing';
	case PROD = 'production';
}
