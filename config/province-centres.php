<?php
// Where each province's map opens when no pin is set yet, as [lng, lat] (the
// order Mapbox takes) and a zoom. The point is the provincial town, not the
// middle of the province: that is where most addresses are.
//
// Keyed by the English names in cambodia-locations.php. locations_offered()
// in config/locations.php adds these to each province it sends.

return [
    'Phnom Penh'       => [[104.9160, 11.5564], 13],
    'Banteay Meanchey' => [[102.9730, 13.5850], 13],
    'Battambang'       => [[103.2020, 13.0950], 13],
    'Kampong Cham'     => [[105.4640, 11.9930], 13],
    'Kampong Chhnang'  => [[104.6660, 12.2500], 13],
    'Kampong Speu'     => [[104.5200, 11.4560], 13],
    'Kampong Thom'     => [[104.8890, 12.7110], 13],
    'Kampot'           => [[104.1810, 10.6100], 13],
    'Kandal'           => [[104.9460, 11.4830], 13],
    'Kep'              => [[104.3170, 10.4830], 13],
    'Koh Kong'         => [[102.9830, 11.6170], 13],
    'Kratie'           => [[106.0190, 12.4880], 13],
    'Mondulkiri'       => [[107.1880, 12.4550], 13],
    'Oddar Meanchey'   => [[103.5180, 14.1810], 13],
    'Pailin'           => [[102.6090, 12.8490], 13],
    'Preah Sihanouk'   => [[103.5220, 10.6270], 13],
    'Preah Vihear'     => [[104.9800, 13.8070], 13],
    'Prey Veng'        => [[105.3250, 11.4840], 13],
    'Pursat'           => [[103.9190, 12.5390], 13],
    'Ratanakiri'       => [[106.9870, 13.7390], 13],
    'Siem Reap'        => [[103.8600, 13.3620], 13],
    'Stung Treng'      => [[105.9690, 13.5260], 13],
    'Svay Rieng'       => [[105.8000, 11.0880], 13],
    'Takeo'            => [[104.7850, 10.9910], 13],
    'Tbong Khmum'      => [[105.6560, 11.9120], 13],
];
