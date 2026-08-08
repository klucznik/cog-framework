// Override or Create New Properties and Variables
	// For performance reasons, these variables and __set and __get override methods
	// are commented out.  But if you wish to implement or override any
	// of the data generated properties, please feel free to uncomment them.
/*
	protected $strSomeNewProperty;

	public function __get($name) {
		switch ($name) {
			case 'SomeNewProperty': return $this->strSomeNewProperty;

			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	public function __set($name, $value) {
		switch ($name) {
			case 'SomeNewProperty':
				try {
					return ($this->strSomeNewProperty = Type::cast($value, Type::STRING));
				} catch (InvalidCastException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

			default:
				try {
					return (parent::__set($name, $value));
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
*/