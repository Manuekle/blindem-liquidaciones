<?php

namespace App\Services\Ai\Simulated;

/**
 * Reads free text (an email, a resignation letter, contract notes) and maps what it recognises to
 * contract form fields, matching people, companies and catalogs by their registered values.
 */
class ContractExtraction
{
    public const LABELS = [
        'worker_id' => 'Trabajador', 'company_id' => 'Empresa', 'job_title_id' => 'Cargo', 'contract_type_id' => 'Tipo de contrato',
        'start_date' => 'Fecha de ingreso', 'end_date' => 'Fecha de retiro', 'agreed_end_date' => 'Fecha de finalización pactada',
        'status' => 'Estado', 'termination_reason_id' => 'Motivo de retiro', 'basic_salary' => 'Salario básico',
        'transport_allowance' => 'Auxilio de transporte', 'average_extras' => 'Horas extras y descansos',
        'average_surcharges' => 'Recargos', 'advances' => 'Préstamos / anticipos', 'other_deductions' => 'Otras deducciones',
    ];

    private const MONTHS = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];

    private const AMOUNT = '(\$?\s*\d[\d.,]*\s*(?:millones|millon|mil\b)?)';

    private array $fields = [];

    private array $found = [];

    private array $notes = [];

    public function __invoke(array $input): array
    {
        $text = (string) ($input['text'] ?? '');
        $plain = preg_replace('/\bprimero (de )?(?=(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre))/', '1 de ', Text::fold($text));
        $catalogs = $input['catalogs'] ?? [];

        $this->worker($plain, $catalogs['workers'] ?? []);
        $this->company($plain, $catalogs['companies'] ?? []);
        $this->byName($plain, $catalogs['job_titles'] ?? [], 'job_title_id');
        $this->contractType($plain, $catalogs['contract_types'] ?? []);
        $this->reason($plain, $catalogs['termination_reasons'] ?? []);
        $this->dates($plain);
        $this->amounts($plain, $catalogs['min_wages'] ?? []);

        if (isset($this->fields['termination_reason_id']) || isset($this->fields['end_date'])) {
            $this->set('status', 'terminated', 'Retirado', 'Menciona retiro o fecha de salida');
        } elseif ($this->fields) {
            $this->set('status', 'active', 'Activo', 'No menciona retiro');
        }

        $missing = array_values(array_diff_key(array_intersect_key(self::LABELS, array_flip(['worker_id', 'company_id', 'job_title_id', 'contract_type_id', 'start_date', 'basic_salary'])), $this->fields));

        return [
            'fields' => $this->fields,
            'found' => $this->found,
            'missing' => $missing,
            'notes' => $this->notes,
            'confidence' => round(count(array_intersect_key($this->fields, array_flip(['worker_id', 'company_id', 'job_title_id', 'contract_type_id', 'start_date', 'basic_salary']))) / 6, 2),
        ];
    }

    private function set(string $field, mixed $value, string $display, string $evidence): void
    {
        $this->fields[$field] = $value;
        $this->found[] = ['field' => $field, 'label' => self::LABELS[$field], 'value' => $display, 'evidence' => $evidence];
    }

    private function worker(string $plain, array $workers): void
    {
        if (preg_match('/\b(?:cc|ce|ti|pas|ppt|c\.c\.?|cedula(?: de ciudadania| de extranjeria)?|documento)\s*(?:n(?:o|ro|umero)?\.?|#|:)?\s*(\d[\d.\s]{3,14}\d)/', $plain, $m)) {
            $document = preg_replace('/\D/', '', $m[1]);
            foreach ($workers as $worker) {
                if (preg_replace('/\D/', '', (string) $worker['document']) === $document) {
                    $this->set('worker_id', $worker['id'], $worker['name'], 'Documento '.$document);

                    return;
                }
            }
            $this->notes[] = "El documento {$document} no está registrado. Registra al trabajador y vuelve a intentarlo.";
        }
        foreach ($workers as $worker) {
            $tokens = array_values(array_filter(explode(' ', Text::fold($worker['name'])), fn ($t) => strlen($t) > 2));
            $hits = count(array_filter($tokens, fn ($t) => preg_match('/\b'.preg_quote($t, '/').'\b/', $plain)));
            if ($tokens && ($hits === count($tokens) || $hits >= 2)) {
                $this->set('worker_id', $worker['id'], $worker['name'], 'Nombre mencionado');

                return;
            }
        }
    }

    private function company(string $plain, array $companies): void
    {
        if (preg_match('/\bnit\s*[:.#]?\s*([\d.\-\s]{6,16}\d)/', $plain, $m)) {
            $nit = substr(preg_replace('/\D/', '', $m[1]), 0, 9);
            foreach ($companies as $company) {
                if (str_starts_with(preg_replace('/\D/', '', (string) $company['nit']), $nit)) {
                    $this->set('company_id', $company['id'], $company['name'], 'Nit '.$m[1]);

                    return;
                }
            }
        }
        foreach ($companies as $company) {
            $core = trim(preg_replace('/\b(s\.?a\.?s\.?|s\.?a\.?|ltda\.?|s\.?\s?en\s?c\.?)$/', '', Text::fold($company['name'])), ' .');
            if ($core !== '' && str_contains($plain, $core)) {
                $this->set('company_id', $company['id'], $company['name'], 'Nombre de la empresa');

                return;
            }
        }
    }

    private function byName(string $plain, array $items, string $field): void
    {
        foreach ($items as $item) {
            if (str_contains($plain, Text::fold($item['name']))) {
                $this->set($field, $item['id'], $item['name'], 'Cargo mencionado');

                return;
            }
        }
    }

    private function contractType(string $plain, array $types): void
    {
        $code = match (true) {
            (bool) preg_match('/indefinid/', $plain) => 'indefinido',
            (bool) preg_match('/termino fijo|a termino definido|contrato fijo/', $plain) => 'fijo',
            (bool) preg_match('/obra o labor|obra labor|por obra/', $plain) => 'obra',
            default => null,
        };
        foreach ($types as $type) {
            if ($code && ($type['code'] === $code || $type['method'] === $code)) {
                $this->set('contract_type_id', $type['id'], $type['name'], 'Modalidad indicada en el texto');

                return;
            }
        }
    }

    private function reason(string $plain, array $reasons): void
    {
        $code = match (true) {
            (bool) preg_match('/sin justa causa|despido injustificado/', $plain) => 'sin_justa_causa',
            (bool) preg_match('/justa causa/', $plain) => 'justa_causa',
            (bool) preg_match('/renunci/', $plain) => 'renuncia',
            (bool) preg_match('/mutuo acuerdo/', $plain) => 'mutuo_acuerdo',
            (bool) preg_match('/vencimiento|no renovaci|vencio el (?:plazo|termino)/', $plain) => 'vencimiento',
            default => null,
        };
        foreach ($reasons as $reason) {
            if ($code && $reason['code'] === $code) {
                $this->set('termination_reason_id', $reason['id'], $reason['name'], 'Motivo descrito en el texto');

                return;
            }
        }
    }

    private function dates(string $plain): void
    {
        $months = implode('|', array_keys(self::MONTHS));
        preg_match_all('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b|\b(\d{4})-(\d{2})-(\d{2})\b|\b(\d{1,2})\s+(?:de\s+)?('.$months.')\s+(?:de\s+|del\s+)?(\d{4})\b/', $plain, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $previousEnd = 0;
        $pending = [];
        foreach ($matches as $m) {
            [$raw, $offset] = $m[0];
            $iso = match (true) {
                ($m[1][0] ?? '') !== '' => $this->iso((int) $m[3][0], (int) $m[2][0], (int) $m[1][0]),
                ($m[4][0] ?? '') !== '' => $this->iso((int) $m[4][0], (int) $m[5][0], (int) $m[6][0]),
                default => $this->iso((int) $m[9][0], self::MONTHS[$m[8][0]], (int) $m[7][0]),
            };
            $context = substr($plain, max($previousEnd, $offset - 60), $offset - max($previousEnd, $offset - 60));
            $previousEnd = $offset + strlen($raw);
            if (! $iso) {
                continue;
            }
            $field = match (true) {
                (bool) preg_match('/pactad|vence|plazo|duracion|hasta el termino|finalizacion/', $context) => 'agreed_end_date',
                (bool) preg_match('/retir|termin|renunci|salida|desvincul|ultimo dia|liquidar|despid|hasta/', $context) => 'end_date',
                (bool) preg_match('/ingres|inici|desde|vincul|comenz|contratad|labora/', $context) => 'start_date',
                default => null,
            };
            if ($field && ! isset($this->fields[$field])) {
                $this->set($field, $iso, Text::date($iso), trim($raw));
            } else {
                $pending[] = [$iso, trim($raw)];
            }
        }
        foreach (['start_date', 'end_date'] as $field) {
            if (! isset($this->fields[$field]) && $pending) {
                [$iso, $raw] = array_shift($pending);
                $this->set($field, $iso, Text::date($iso), $raw.' (por orden en el texto)');
            }
        }
    }

    private function iso(int $year, int $month, int $day): ?string
    {
        return checkdate($month, $day, $year) && $year >= 1950 ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    private function amounts(string $plain, array $minWages): void
    {
        $patterns = [
            'transport_allowance' => 'auxilio de transporte|subsidio de transporte',
            'average_extras' => 'horas extra\w*|extras',
            'average_surcharges' => 'recargos?(?: nocturnos?)?',
            'advances' => 'anticipos?|prestamos?|adelantos?',
            'other_deductions' => 'otras deducciones|descuentos?',
            'basic_salary' => 'salario(?: basico)?(?: mensual)?|sueldo|devenga(?:ba)?|asignacion(?: basica)?',
        ];
        foreach ($patterns as $field => $keywords) {
            if (preg_match('/(?:'.$keywords.')(?:\s+(?:de|por|es|era|fue|del|mensual|basico|un total de|equivalente a|:))*\s*'.self::AMOUNT.'/', $plain, $m)) {
                $value = $this->amount($m[1]);
                if ($value >= 1000) {
                    $this->set($field, $value, Text::money($value), trim($m[0]));
                }
            }
        }
        if (! isset($this->fields['basic_salary']) && preg_match('/salario minimo|un smmlv|1 smmlv/', $plain)) {
            $year = (int) substr($this->fields['end_date'] ?? $this->fields['start_date'] ?? date('Y-m-d'), 0, 4);
            if (isset($minWages[$year])) {
                $this->set('basic_salary', (int) $minWages[$year], Text::money($minWages[$year]), 'Salario mínimo '.$year);
            }
        }
    }

    private function amount(string $raw): int
    {
        $raw = trim(str_replace('$', '', $raw));
        if (preg_match('/^([\d.,]+)\s*(millones|millon|mil)$/', $raw, $m)) {
            $number = (float) str_replace(',', '.', preg_match('/^\d{1,3}(\.\d{3})+$/', $m[1]) ? str_replace('.', '', $m[1]) : $m[1]);

            return (int) round($number * ($m[2] === 'mil' ? 1000 : 1000000));
        }

        return (int) preg_replace('/\D/', '', preg_replace('/[.,]\d{1,2}$/', '', $raw));
    }
}
