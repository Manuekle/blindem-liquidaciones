@php
 // A soft gradient seeded by the name: the same person always gets the same colors, different people rarely match.
 $seed = crc32(mb_strtolower(trim($name)));
 $hue = $seed % 360;
 $style = sprintf('--av-base:hsl(%d 80%% 88%%);--av-mid:hsl(%d 92%% 66%%);--av-hot:hsl(%d 88%% 56%%);--av-x:%d%%;--av-y:%d%%',
  ($hue + 25) % 360, ($hue + 45) % 360, $hue, 55 + ($seed >> 9) % 25, 55 + ($seed >> 17) % 25);
@endphp
<span class="avatar gradient {{ $variant ?? '' }}" style="{{ $style }}" aria-hidden="true"></span>
