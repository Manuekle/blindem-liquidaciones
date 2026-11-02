{{-- Search and link-preview metadata (Google, LinkedIn, WhatsApp, X, Slack). Only the sign-in screen is public:
     internal and error layouts pass $robots = 'noindex, nofollow'. Pages may override @section('description'). --}}
@php
 $seoSite = 'Blindem Liquidaciones';
 $seoDescription = trim($__env->yieldContent('description')) ?: 'Calcula liquidaciones laborales en Colombia: cesantías, prima, vacaciones e indemnización con la norma vigente de cada año. Un asistente de IA local explica cada cifra.';
 $seoShareTitle = 'Liquidación laboral en Colombia, explicada por IA local · '.$seoSite;
 $seoUrl = url()->current();
 $seoImage = asset('og-image.png');
 $seoSchema = [
  '@context' => 'https://schema.org',
  '@type' => 'WebApplication',
  'name' => $seoSite,
  'url' => url('/'),
  'description' => $seoDescription,
  'applicationCategory' => 'BusinessApplication',
  'operatingSystem' => 'Web',
  'inLanguage' => 'es-CO',
  'image' => $seoImage,
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'COP'],
 ];
@endphp
 <meta name="description" content="{{ $seoDescription }}">
 <meta name="robots" content="{{ $robots ?? 'index, follow, max-image-preview:large' }}">
 <link rel="canonical" href="{{ $seoUrl }}">
 <meta name="application-name" content="{{ $seoSite }}">
 <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
 <meta name="theme-color" content="#121211" media="(prefers-color-scheme: dark)">
 <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
 <meta property="og:type" content="website">
 <meta property="og:site_name" content="{{ $seoSite }}">
 <meta property="og:locale" content="es_CO">
 <meta property="og:title" content="{{ $seoShareTitle }}">
 <meta property="og:description" content="{{ $seoDescription }}">
 <meta property="og:url" content="{{ $seoUrl }}">
 <meta property="og:image" content="{{ $seoImage }}">
 <meta property="og:image:secure_url" content="{{ $seoImage }}">
 <meta property="og:image:type" content="image/png">
 <meta property="og:image:width" content="1200">
 <meta property="og:image:height" content="630">
 <meta property="og:image:alt" content="Explicación de IA de una liquidación: neto, porcentaje por concepto y texto en lenguaje claro">
 <meta name="twitter:card" content="summary_large_image">
 <meta name="twitter:title" content="{{ $seoShareTitle }}">
 <meta name="twitter:description" content="{{ $seoDescription }}">
 <meta name="twitter:image" content="{{ $seoImage }}">
 <meta name="twitter:image:alt" content="Explicación de IA de una liquidación: neto, porcentaje por concepto y texto en lenguaje claro">
@if(($robots ?? null) === null)
 <script type="application/ld+json">{!! json_encode($seoSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
