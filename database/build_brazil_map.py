"""
Gera app/Core/BrazilMapData.php: contornos reais dos 27 estados brasileiros, prontos como
paths SVG (nao um cartograma em grade) -- um "mapa nacional" com escala unica compartilhada,
mais um path por estado (zoomado, bbox proprio) pro drill-down clicavel de Equipe Nacional.

Fonte dos dados: click_that_hood/brazil-states.geojson (poligonos por estado, sigla no
campo 'properties.sigla'). Roda 1x, offline -- nao faz parte do fluxo da aplicacao.

Uso:
    curl -sL -o brazil-states.geojson \
        https://raw.githubusercontent.com/codeforamerica/click_that_hood/master/public/data/brazil-states.geojson
    python build_brazil_map.py brazil-states.geojson ../app/Core/BrazilMapData.php preview.html
"""
import json
import math
import sys

IN_PATH = sys.argv[1]
OUT_PHP = sys.argv[2]
OUT_PREVIEW = sys.argv[3]

with open(IN_PATH, encoding='utf-8') as f:
    data = json.load(f)


def rdp(points, epsilon):
    """Ramer-Douglas-Peucker: simplifica uma polilinha mantendo a forma geral."""
    if len(points) < 3:
        return points
    start, end = points[0], points[-1]
    dmax = 0.0
    index = 0
    x1, y1 = start
    x2, y2 = end
    dx, dy = x2 - x1, y2 - y1
    norm = math.hypot(dx, dy)
    for i in range(1, len(points) - 1):
        px, py = points[i]
        if norm == 0:
            d = math.hypot(px - x1, py - y1)
        else:
            d = abs(dy * px - dx * py + x2 * y1 - y2 * x1) / norm
        if d > dmax:
            index = i
            dmax = d
    if dmax > epsilon:
        left = rdp(points[:index + 1], epsilon)
        right = rdp(points[index:], epsilon)
        return left[:-1] + right
    return [start, end]


def outer_rings_of(geometry):
    """So o contorno externo de cada parte do poligono (ignora buracos -- fronteira de
    estado nao tem buraco relevante pra esse uso, e evitaria precisar de fill-rule)."""
    polys = [geometry['coordinates']] if geometry['type'] == 'Polygon' else geometry['coordinates']
    for poly in polys:
        if poly:
            yield poly[0]


states = {feat['properties']['sigla']: feat['geometry'] for feat in data['features']}

# ---- 1) bbox nacional (escala unica, pra manter o tamanho relativo real entre estados)
all_lons = [lon for geom in states.values() for ring in outer_rings_of(geom) for lon, lat in ring]
all_lats = [lat for geom in states.values() for ring in outer_rings_of(geom) for lon, lat in ring]
min_lon, max_lon = min(all_lons), max(all_lons)
min_lat, max_lat = min(all_lats), max(all_lats)
lat0 = (min_lat + max_lat) / 2
cos_lat0 = math.cos(math.radians(lat0))

NAT_W, NAT_H, NAT_PAD = 640, 700, 6
dx, dy = (max_lon - min_lon) * cos_lat0, (max_lat - min_lat)
nat_scale = min((NAT_W - 2 * NAT_PAD) / dx, (NAT_H - 2 * NAT_PAD) / dy)
nat_x_off = (NAT_W - 2 * NAT_PAD - dx * nat_scale) / 2
nat_y_off = (NAT_H - 2 * NAT_PAD - dy * nat_scale) / 2


def nat_project(lon, lat):
    x = NAT_PAD + nat_x_off + (lon - min_lon) * cos_lat0 * nat_scale
    y = NAT_PAD + nat_y_off + (max_lat - lat) * nat_scale
    return x, y


def build_path(geometry, project, epsilon_deg):
    parts = []
    for ring in outer_rings_of(geometry):
        simplified = rdp(ring, epsilon_deg)
        if len(simplified) < 3:
            continue
        pts = [project(lon, lat) for lon, lat in simplified]
        parts.append('M' + ' L'.join(f'{x:.2f},{y:.2f}' for x, y in pts) + ' Z')
    return ' '.join(parts)


national_paths = {uf: build_path(geom, nat_project, 0.035) for uf, geom in states.items()}

# ---- 2) path por estado, zoomado no proprio bbox (projecao propria, guardada junto pro JS
# reproduzir a mesma transformacao ao plotar cidades reais por cima do contorno)
STATE_W, STATE_H, STATE_PAD = 640, 560, 16
state_paths = {}
for uf, geom in states.items():
    lons = [lon for ring in outer_rings_of(geom) for lon, lat in ring]
    lats = [lat for ring in outer_rings_of(geom) for lon, lat in ring]
    s_min_lon, s_max_lon, s_min_lat, s_max_lat = min(lons), max(lons), min(lats), max(lats)
    s_lat0 = (s_min_lat + s_max_lat) / 2
    s_cos = math.cos(math.radians(s_lat0))
    s_dx = (s_max_lon - s_min_lon) * s_cos or 0.0001
    s_dy = (s_max_lat - s_min_lat) or 0.0001
    s_scale = min((STATE_W - 2 * STATE_PAD) / s_dx, (STATE_H - 2 * STATE_PAD) / s_dy)
    s_x_off = (STATE_W - 2 * STATE_PAD - s_dx * s_scale) / 2
    s_y_off = (STATE_H - 2 * STATE_PAD - s_dy * s_scale) / 2

    def s_project(lon, lat, _ml=s_min_lon, _mla=s_max_lat, _c=s_cos, _s=s_scale, _xo=s_x_off, _yo=s_y_off):
        return (STATE_PAD + _xo + (lon - _ml) * _c * _s, STATE_PAD + _yo + (_mla - lat) * _s)

    state_paths[uf] = {
        'd': build_path(geom, s_project, 0.006),
        'minLon': s_min_lon, 'maxLat': s_max_lat, 'cos': s_cos,
        'scale': s_scale, 'xOff': s_x_off, 'yOff': s_y_off, 'pad': STATE_PAD,
    }


def php_str(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"


lines = [
    '<?php', '', 'namespace App\\Core;', '',
    '/**',
    ' * Contornos reais dos 27 estados brasileiros (fonte: click_that_hood/brazil-states.geojson,',
    ' * IBGE-based), simplificados (Ramer-Douglas-Peucker) e projetados em SVG (equirretangular',
    ' * com correcao de longitude por cos(latitude media) -- pra nao esticar o pais no sentido',
    ' * horizontal). Gerado 1x por database/build_brazil_map.py, nao em runtime.',
    ' */',
    'class BrazilMapData', '{',
    f'    public const NATIONAL_VIEWBOX = "0 0 {NAT_W} {NAT_H}";', '',
    '    /** @var array<string,string> UF => SVG path "d", mesmo viewBox pra todo mundo (escala unica) */',
    '    public const NATIONAL_PATHS = [',
]
for uf in sorted(national_paths):
    lines.append(f'        {php_str(uf)} => {php_str(national_paths[uf])},')
lines += [
    '    ];', '',
    f'    public const STATE_VIEWBOX = "0 0 {STATE_W} {STATE_H}";', '',
    '    /**',
    '     * @var array<string,array{d:string,minLon:float,maxLat:float,cos:float,scale:float,xOff:float,yOff:float,pad:float}>',
    '     * Path + parametros de projecao (pra reproduzir a mesma projecao no JS ao plotar',
    '     * as cidades daquele estado por cima do contorno, com base em lat/lng reais).',
    '     */',
    '    public const STATE_PATHS = [',
]
for uf in sorted(state_paths):
    sp = state_paths[uf]
    lines.append(
        f"        {php_str(uf)} => ['d' => {php_str(sp['d'])}, "
        f"'minLon' => {sp['minLon']:.6f}, 'maxLat' => {sp['maxLat']:.6f}, "
        f"'cos' => {sp['cos']:.6f}, 'scale' => {sp['scale']:.6f}, "
        f"'xOff' => {sp['xOff']:.3f}, 'yOff' => {sp['yOff']:.3f}, 'pad' => {sp['pad']:.1f}],"
    )
lines += ['    ];', '}', '']

with open(OUT_PHP, 'w', encoding='utf-8', newline='\n') as f:
    f.write('\n'.join(lines))

preview = [f'<svg viewBox="0 0 {NAT_W} {NAT_H}" xmlns="http://www.w3.org/2000/svg" style="background:#eef1f6">']
for uf, d in national_paths.items():
    preview.append(f'<path d="{d}" fill="#cdd6e4" stroke="#fff" stroke-width="1"><title>{uf}</title></path>')
preview.append('</svg>')
with open(OUT_PREVIEW, 'w', encoding='utf-8') as f:
    f.write('<html><body>' + ''.join(preview) + '</body></html>')

print('states:', len(national_paths), '| national bbox lon/lat:', min_lon, max_lon, min_lat, max_lat)
