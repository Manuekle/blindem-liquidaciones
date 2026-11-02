<?php

namespace App\Services\Ai\Simulated;

/**
 * Converts a request like "retirados de Servicios Andinos" into the contract list filters.
 */
class SearchInterpretation
{
    public function __invoke(array $input): array
    {
        $plain = Text::fold((string) $input['query']);
        $filters = [];
        $understood = [];

        if (preg_match('/\b(activ|vigent)/', $plain)) {
            $filters['status'] = 'active';
            $understood[] = 'Estado: activo';
        } elseif (preg_match('/\b(retirad|terminad|inactiv|liquidad|desvinculad)/', $plain)) {
            $filters['status'] = 'terminated';
            $understood[] = 'Estado: retirado';
        }
        foreach ($input['catalogs']['companies'] as $company) {
            $core = trim(preg_replace('/\b(s\.?a\.?s\.?|s\.?a\.?|ltda\.?)$/', '', Text::fold($company['name'])), ' .');
            $first = explode(' ', $core)[0];
            if ($core !== '' && (str_contains($plain, $core) || (strlen($first) > 4 && preg_match('/\b'.preg_quote($first, '/').'\b/', $plain)))) {
                $filters['company'] = $company['id'];
                $understood[] = 'Empresa: '.$company['name'];
                break;
            }
        }
        foreach ($input['catalogs']['document_types'] as $type) {
            if (preg_match('/\b'.preg_quote(Text::fold($type['code']), '/').'\b/', $plain)) {
                $filters['document_type'] = $type['id'];
                $understood[] = 'Documento: '.$type['code'];
                break;
            }
        }
        if (preg_match('/\b(\d{5,12})\b/', $plain, $m)) {
            $filters['document'] = $m[1];
            $understood[] = 'Número: '.$m[1];
        }
        foreach ($input['catalogs']['workers'] as $worker) {
            $tokens = array_values(array_filter(explode(' ', Text::fold($worker)), fn ($t) => strlen($t) > 2));
            $hits = array_values(array_filter($tokens, fn ($t) => preg_match('/\b'.preg_quote($t, '/').'\b/', $plain)));
            if ($hits && (count($hits) >= 2 || $hits[0] === $tokens[0])) {
                $filters['q'] = implode(' ', array_slice(explode(' ', $worker), 0, max(1, count($hits))));
                $understood[] = 'Trabajador: '.$filters['q'];
                break;
            }
        }

        return [
            'filters' => $filters,
            'understood' => $understood,
            'message' => $filters ? 'Apliqué '.count($filters).' '.(count($filters) === 1 ? 'filtro' : 'filtros').' a partir de tu búsqueda.' : 'No reconocí filtros en la búsqueda. Prueba con un estado, una empresa, un nombre o un documento.',
        ];
    }
}
