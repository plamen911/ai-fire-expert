<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'Полето :attribute трябва да бъде прието.',
    'accepted_if' => 'Полето :attribute трябва да бъде прието, когато :other е :value.',
    'active_url' => 'Полето :attribute трябва да бъде валиден URL адрес.',
    'after' => 'Полето :attribute трябва да бъде дата след :date.',
    'after_or_equal' => 'Полето :attribute трябва да бъде дата след или равна на :date.',
    'alpha' => 'Полето :attribute трябва да съдържа само букви.',
    'alpha_dash' => 'Полето :attribute трябва да съдържа само букви, цифри, тирета и долни черти.',
    'alpha_num' => 'Полето :attribute трябва да съдържа само букви и цифри.',
    'any_of' => 'Полето :attribute е невалидно.',
    'array' => 'Полето :attribute трябва да бъде масив.',
    'ascii' => 'Полето :attribute трябва да съдържа само еднобайтови буквено-цифрови символи.',
    'before' => 'Полето :attribute трябва да бъде дата преди :date.',
    'before_or_equal' => 'Полето :attribute трябва да бъде дата преди или равна на :date.',
    'between' => [
        'array' => 'Полето :attribute трябва да съдържа между :min и :max елемента.',
        'file' => 'Полето :attribute трябва да бъде между :min и :max килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде между :min и :max.',
        'string' => 'Полето :attribute трябва да бъде между :min и :max символа.',
    ],
    'boolean' => 'Полето :attribute трябва да бъде вярно или невярно.',
    'can' => 'Полето :attribute съдържа неоторизирана стойност.',
    'confirmed' => 'Потвърждението на :attribute не съвпада.',
    'contains' => 'В полето :attribute липсва задължителна стойност.',
    'current_password' => 'Паролата е грешна.',
    'date' => 'Полето :attribute трябва да бъде валидна дата.',
    'date_equals' => 'Полето :attribute трябва да бъде дата, равна на :date.',
    'date_format' => 'Полето :attribute трябва да съответства на формата :format.',
    'decimal' => 'Полето :attribute трябва да има :decimal десетични знака.',
    'declined' => 'Полето :attribute трябва да бъде отхвърлено.',
    'declined_if' => 'Полето :attribute трябва да бъде отхвърлено, когато :other е :value.',
    'different' => 'Полетата :attribute и :other трябва да бъдат различни.',
    'digits' => 'Полето :attribute трябва да бъде :digits цифри.',
    'digits_between' => 'Полето :attribute трябва да бъде между :min и :max цифри.',
    'dimensions' => 'Полето :attribute има невалидни размери на изображението.',
    'distinct' => 'Полето :attribute има дублираща се стойност.',
    'doesnt_contain' => 'Полето :attribute не трябва да съдържа: :values.',
    'doesnt_end_with' => 'Полето :attribute не трябва да завършва с: :values.',
    'doesnt_start_with' => 'Полето :attribute не трябва да започва с: :values.',
    'email' => 'Полето :attribute трябва да бъде валиден имейл адрес.',
    'encoding' => 'Полето :attribute трябва да бъде кодирано в :encoding.',
    'ends_with' => 'Полето :attribute трябва да завършва с: :values.',
    'enum' => 'Избраната стойност за :attribute е невалидна.',
    'exists' => 'Избраната стойност за :attribute е невалидна.',
    'extensions' => 'Полето :attribute трябва да има едно от следните разширения: :values.',
    'file' => 'Полето :attribute трябва да бъде файл.',
    'filled' => 'Полето :attribute трябва да има стойност.',
    'gt' => [
        'array' => 'Полето :attribute трябва да има повече от :value елемента.',
        'file' => 'Полето :attribute трябва да бъде по-голямо от :value килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде по-голямо от :value.',
        'string' => 'Полето :attribute трябва да бъде по-дълго от :value символа.',
    ],
    'gte' => [
        'array' => 'Полето :attribute трябва да има :value или повече елемента.',
        'file' => 'Полето :attribute трябва да бъде по-голямо или равно на :value килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде по-голямо или равно на :value.',
        'string' => 'Полето :attribute трябва да бъде по-дълго или равно на :value символа.',
    ],
    'hex_color' => 'Полето :attribute трябва да бъде валиден шестнадесетичен цвят.',
    'image' => 'Полето :attribute трябва да бъде изображение.',
    'in' => 'Избраната стойност за :attribute е невалидна.',
    'in_array' => 'Полето :attribute трябва да съществува в :other.',
    'in_array_keys' => 'Полето :attribute трябва да съдържа поне един от следните ключове: :values.',
    'integer' => 'Полето :attribute трябва да бъде цяло число.',
    'ip' => 'Полето :attribute трябва да бъде валиден IP адрес.',
    'ipv4' => 'Полето :attribute трябва да бъде валиден IPv4 адрес.',
    'ipv6' => 'Полето :attribute трябва да бъде валиден IPv6 адрес.',
    'json' => 'Полето :attribute трябва да бъде валиден JSON.',
    'list' => 'Полето :attribute трябва да бъде списък.',
    'lowercase' => 'Полето :attribute трябва да бъде с малки букви.',
    'lt' => [
        'array' => 'Полето :attribute трябва да има по-малко от :value елемента.',
        'file' => 'Полето :attribute трябва да бъде по-малко от :value килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде по-малко от :value.',
        'string' => 'Полето :attribute трябва да бъде по-кратко от :value символа.',
    ],
    'lte' => [
        'array' => 'Полето :attribute не трябва да има повече от :value елемента.',
        'file' => 'Полето :attribute трябва да бъде по-малко или равно на :value килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде по-малко или равно на :value.',
        'string' => 'Полето :attribute трябва да бъде по-кратко или равно на :value символа.',
    ],
    'mac_address' => 'Полето :attribute трябва да бъде валиден MAC адрес.',
    'max' => [
        'array' => 'Полето :attribute не трябва да има повече от :max елемента.',
        'file' => 'Полето :attribute не трябва да бъде по-голямо от :max килобайта.',
        'numeric' => 'Полето :attribute не трябва да бъде по-голямо от :max.',
        'string' => 'Полето :attribute не трябва да бъде по-дълго от :max символа.',
    ],
    'max_digits' => 'Полето :attribute не трябва да има повече от :max цифри.',
    'mimes' => 'Полето :attribute трябва да бъде файл от тип: :values.',
    'mimetypes' => 'Полето :attribute трябва да бъде файл от тип: :values.',
    'min' => [
        'array' => 'Полето :attribute трябва да има поне :min елемента.',
        'file' => 'Полето :attribute трябва да бъде поне :min килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде поне :min.',
        'string' => 'Полето :attribute трябва да бъде поне :min символа.',
    ],
    'min_digits' => 'Полето :attribute трябва да има поне :min цифри.',
    'missing' => 'Полето :attribute трябва да липсва.',
    'missing_if' => 'Полето :attribute трябва да липсва, когато :other е :value.',
    'missing_unless' => 'Полето :attribute трябва да липсва, освен ако :other е :value.',
    'missing_with' => 'Полето :attribute трябва да липсва, когато :values присъства.',
    'missing_with_all' => 'Полето :attribute трябва да липсва, когато :values присъстват.',
    'multiple_of' => 'Полето :attribute трябва да бъде кратно на :value.',
    'not_in' => 'Избраната стойност за :attribute е невалидна.',
    'not_regex' => 'Форматът на полето :attribute е невалиден.',
    'numeric' => 'Полето :attribute трябва да бъде число.',
    'password' => [
        'letters' => 'Полето :attribute трябва да съдържа поне една буква.',
        'mixed' => 'Полето :attribute трябва да съдържа поне една главна и една малка буква.',
        'numbers' => 'Полето :attribute трябва да съдържа поне една цифра.',
        'symbols' => 'Полето :attribute трябва да съдържа поне един символ.',
        'uncompromised' => 'Стойността на :attribute се е появила в изтичане на данни. Моля, изберете друга стойност за :attribute.',
    ],
    'present' => 'Полето :attribute трябва да присъства.',
    'present_if' => 'Полето :attribute трябва да присъства, когато :other е :value.',
    'present_unless' => 'Полето :attribute трябва да присъства, освен ако :other е :value.',
    'present_with' => 'Полето :attribute трябва да присъства, когато :values присъства.',
    'present_with_all' => 'Полето :attribute трябва да присъства, когато :values присъстват.',
    'prohibited' => 'Полето :attribute е забранено.',
    'prohibited_if' => 'Полето :attribute е забранено, когато :other е :value.',
    'prohibited_if_accepted' => 'Полето :attribute е забранено, когато :other е прието.',
    'prohibited_if_declined' => 'Полето :attribute е забранено, когато :other е отхвърлено.',
    'prohibited_unless' => 'Полето :attribute е забранено, освен ако :other е в :values.',
    'prohibits' => 'Полето :attribute забранява присъствието на :other.',
    'regex' => 'Форматът на полето :attribute е невалиден.',
    'required' => 'Полето :attribute е задължително.',
    'required_array_keys' => 'Полето :attribute трябва да съдържа записи за: :values.',
    'required_if' => 'Полето :attribute е задължително, когато :other е :value.',
    'required_if_accepted' => 'Полето :attribute е задължително, когато :other е прието.',
    'required_if_declined' => 'Полето :attribute е задължително, когато :other е отхвърлено.',
    'required_unless' => 'Полето :attribute е задължително, освен ако :other е в :values.',
    'required_with' => 'Полето :attribute е задължително, когато :values присъства.',
    'required_with_all' => 'Полето :attribute е задължително, когато :values присъстват.',
    'required_without' => 'Полето :attribute е задължително, когато :values не присъства.',
    'required_without_all' => 'Полето :attribute е задължително, когато нито едно от :values не присъства.',
    'same' => 'Полетата :attribute и :other трябва да съвпадат.',
    'size' => [
        'array' => 'Полето :attribute трябва да съдържа :size елемента.',
        'file' => 'Полето :attribute трябва да бъде :size килобайта.',
        'numeric' => 'Полето :attribute трябва да бъде :size.',
        'string' => 'Полето :attribute трябва да бъде :size символа.',
    ],
    'starts_with' => 'Полето :attribute трябва да започва с: :values.',
    'string' => 'Полето :attribute трябва да бъде текст.',
    'timezone' => 'Полето :attribute трябва да бъде валидна часова зона.',
    'unique' => 'Стойността на :attribute вече е заета.',
    'uploaded' => 'Качването на :attribute е неуспешно.',
    'uppercase' => 'Полето :attribute трябва да бъде с главни букви.',
    'url' => 'Полето :attribute трябва да бъде валиден URL адрес.',
    'ulid' => 'Полето :attribute трябва да бъде валиден ULID.',
    'uuid' => 'Полето :attribute трябва да бъде валиден UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'име',
        'email' => 'имейл',
        'password' => 'парола',
        'password_confirmation' => 'потвърждение на парола',
        'position' => 'длъжност',
    ],

];
