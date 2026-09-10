// Override or Create New Properties and Variables
	// Columns and references are typed properties on the generated class: a new
	// property is a plain declaration, a computed one is a virtual hooked property,
	// and a generated reference can be redeclared with hooks that defer to it.
/*
	public ?string $someNewProperty = null;

	public string $someComputedProperty {
		get => trim($this->firstName . ' ' . $this->lastName);
	}

	public ?SomeClass $someReference {
		get => parent::$someReference::get() ?? SomeClass::load(1);
		set {
			parent::$someReference::set($value);
		}
	}
*/