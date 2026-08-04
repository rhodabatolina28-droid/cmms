// Division abbreviation lookup — maps full office/division names to short codes
export function getDivisionAbbr(office) {
    if (!office) return '';
    const key = office.toLowerCase().trim();
    const map = {
        'research and information division': 'RID',
        'research and info division': 'RID',
        'rid': 'RID',
        'administrative division': 'AD',
        'administrative': 'AD', 'ad': 'AD',
        'financial and management division': 'FMD',
        'financial and management': 'FMD', 'fmd': 'FMD',
        'conciliation and mediation division': 'CMD',
        'conciliation-mediation': 'CMD',
        'conciliation and mediation': 'CMD', 'cmd': 'CMD',
        'commission on audit': 'COA', 'coa': 'COA',
        'technical services department': 'TSD',
        'technical services': 'TSD', 'tsd': 'TSD',
        'voluntary arbitration division': 'VAD',
        'voluntary arbitration program': 'VAD',
        'voluntary arbitration': 'VAD', 'vad': 'VAD',
        'office of the executive director': 'OED',
        'office of executive director': 'OED', 'oed': 'OED',
        'workplace relations enhancement division': 'WRED',
        'workplace relations and enhancement division': 'WRED',
        'workplace relations enhancement': 'WRED', 'wred': 'WRED',
        'internal services department': 'ISD',
        'internal services': 'ISD', 'isd': 'ISD',
    };
    return map[key] || '';
}

// Branch options per region (for the inventory modal)
export const INVENTORY_BRANCH_MAP = {
    'NCR':        ['NCMB Main Office', 'RCMB-NCR'],
    'CAR':        ['RCMB-CAR'],
    'Region I':   ['RCMB-I (Ilocos Region)'],
    'Region II':  ['RCMB-II (Cagayan Valley)'],
    'Region III': ['RCMB-III (Central Luzon)'],
    'Region IV-A':['RCMB-IV-A (CALABARZON)'],
    'Region IV-B':['RCMB-IV-B (MIMAROPA)'],
    'Region V':   ['RCMB-V (Bicol Region)'],
    'Region VI':  ['RCMB-VI (Western Visayas)'],
    'Region VII': ['RCMB-VII (Central Visayas)'],
    'Region VIII':['RCMB-VIII (Eastern Visayas)'],
    'Region IX':  ['RCMB-IX (Zamboanga Peninsula)'],
    'Region X':   ['RCMB-X (Northern Mindanao)'],
    'Region XI':  ['RCMB-XI (Davao Region)'],
    'Region XII': ['RCMB-XII (SOCCSKSARGEN)'],
    'Region XIII':['RCMB-XIII (Caraga)'],
    'BARMM':      ['RCMB-BARMM'],
};