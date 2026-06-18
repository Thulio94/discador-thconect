<?php

namespace Tests\Unit;

use App\Services\Campaigns\ServiceFieldValidator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ServiceFieldValidatorTest extends TestCase
{
    private ServiceFieldValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ServiceFieldValidator;
    }

    public function test_it_normalizes_valid_schema_values_and_ignores_unknown_keys(): void
    {
        $this->assertFalse((new ReflectionMethod(ServiceFieldValidator::class, 'validate'))->isStatic());

        $schema = [
            ['key' => 'nome', 'type' => 'text', 'label' => 'Nome do cliente', 'required' => true],
            ['key' => 'quantidade', 'type' => 'number', 'label' => 'Quantidade'],
            ['key' => 'taxa', 'type' => 'number', 'label' => 'Taxa'],
            ['key' => 'nascimento', 'type' => 'date', 'label' => 'Data de nascimento'],
        ];

        $result = $this->validator->validate($schema, [
            'nome' => '  Cliente  ',
            'quantidade' => '12,5',
            'taxa' => '12.50',
            'nascimento' => '2026-06-14',
            'fora_schema' => 'ignorar',
        ]);

        $this->assertTrue($result->passes());
        $this->assertSame([
            'nome' => 'Cliente',
            'quantidade' => '12.5',
            'taxa' => '12.50',
            'nascimento' => '2026-06-14',
        ], $result->values);
        $this->assertSame([], $result->errors);
    }

    public function test_it_returns_exact_errors_and_no_values_for_invalid_fields(): void
    {
        $schema = [
            ['key' => 'nome', 'label' => 'Nome do cliente', 'required' => true],
            ['key' => 'numero', 'type' => 'number', 'label' => 'Numero'],
            ['key' => 'data', 'type' => 'date', 'label' => 'Data de nascimento'],
        ];

        $result = $this->validator->validate($schema, [
            'nome' => '   ',
            'numero' => 'doze',
            'data' => '2026-02-30',
        ]);

        $this->assertFalse($result->passes());
        $this->assertSame([
            'service_fields.nome' => 'Informe Nome do cliente.',
            'service_fields.numero' => 'Numero possui numero invalido.',
            'service_fields.data' => 'Data de nascimento possui data invalida.',
        ], $result->errors);
        $this->assertSame([], $result->values);
    }

    public function test_required_number_accepts_zero_and_optional_blank_text_is_omitted(): void
    {
        $schema = [
            ['key' => 'quantidade', 'type' => 'number', 'required' => true],
            ['key' => 'observacao', 'type' => 'text'],
        ];

        $result = $this->validator->validate($schema, [
            'quantidade' => '0',
            'observacao' => '   ',
        ]);

        $this->assertTrue($result->passes());
        $this->assertSame(['quantidade' => '0'], $result->values);
        $this->assertSame([], $result->errors);
    }

    public function test_text_longer_than_255_characters_is_invalid(): void
    {
        $result = $this->validator->validate([
            ['key' => 'observacao', 'type' => 'text', 'label' => 'Observacao'],
        ], [
            'observacao' => str_repeat('a', 256),
        ]);

        $this->assertFalse($result->passes());
        $this->assertSame([
            'service_fields.observacao' => 'Observacao deve ter no maximo 255 caracteres.',
        ], $result->errors);
        $this->assertSame([], $result->values);
    }
}
