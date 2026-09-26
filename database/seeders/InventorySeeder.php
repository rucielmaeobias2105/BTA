<?php

namespace Database\Seeders;

use App\Enums\ItemTag;
use App\Models\InventoryItem;
use App\Models\Service;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // name, sku, category, qty, unit, reorder threshold, supplier, tag
            ['L\'Oréal Elvive Hair Repair Shampoo', 'INV-HC-001', 'Hair Care', 14, 'bottles', 5, 'Beauty Depot PH', ItemTag::BestSeller],
            ['L\'Oréal Elvive Conditioner', 'INV-HC-002', 'Hair Care', 9, 'bottles', 5, 'Beauty Depot PH', ItemTag::Available],
            ['Matsui Hair Wax Matte', 'INV-ST-001', 'Styling', 3, 'pcs', 5, 'Salon Essentials', ItemTag::LowStock],
            ['Nikol Ear Candy Gel', 'INV-ST-002', 'Styling', 22, 'pcs', 8, 'Salon Essentials', ItemTag::Available],
            ['Bebird Venus Precision Tip', 'INV-ST-003', 'Styling', 1, 'pcs', 6, 'Salon Essentials', ItemTag::LowStock],
            ['OPI Infinite Shine Gel Color', 'INV-NC-001', 'Nail Care', 18, 'pcs', 6, 'Beauty Depot PH', ItemTag::Available],
            ['Gelish Base Gel', 'INV-NC-002', 'Nail Care', 4, 'bottles', 4, 'Beauty Depot PH', ItemTag::LowStock],
            ['Gelish Top Coat', 'INV-NC-003', 'Nail Care', 2, 'bottles', 4, 'Beauty Depot PH', ItemTag::LowStock],
            ['Sally Hansen Hard as Nails', 'INV-NC-004', 'Nail Care', 0, 'pcs', 3, 'Beauty Depot PH', ItemTag::SoldOut],
            ['Revlonlash Volume Fantasy Mascara', 'INV-LB-001', 'Lash & Brow', 11, 'pcs', 5, 'Watsons PH', ItemTag::Available],
            ['Refillable Lash Glue 5ml', 'INV-LB-002', 'Lash & Brow', 6, 'pcs', 4, 'Watsons PH', ItemTag::Available],
            ['Cezanne Lasting Finish Brow Set', 'INV-LB-003', 'Lash & Brow', 5, 'pcs', 4, 'Watsons PH', ItemTag::Available],
            ['Nivea Facial Serum', 'INV-SK-001', 'Skincare', 7, 'pcs', 4, 'Watsons PH', ItemTag::Available],
            ['Nivea Micellar Cleansing Water', 'INV-SK-002', 'Skincare', 2, 'pcs', 5, 'Watsons PH', ItemTag::LowStock],
            ['Aloe Vera Gel (raw)', 'INV-MA-001', 'Massage & Spa', 1200, 'ml', 500, 'Herbalista PH', ItemTag::Available],
            ['Virgin Coconut Oil', 'INV-MA-002', 'Massage & Spa', 850, 'ml', 500, 'Herbalista PH', ItemTag::Available],
            ['Rose Essential Oil', 'INV-MA-003', 'Massage & Spa', 60, 'ml', 100, 'Herbalista PH', ItemTag::LowStock],
            ['St. Basil Steam Aroma', 'INV-MA-004', 'Massage & Spa', 30, 'pcs', 10, 'Herbalista PH', ItemTag::Available],
            ['70% Isopropyl Alcohol', 'INV-DS-001', 'Disinfectants', 3200, 'ml', 1000, 'Watsons PH', ItemTag::Available],
            ['Barbicide Disinfectant Solution', 'INV-DS-002', 'Disinfectants', 950, 'ml', 1000, 'Beauty Depot PH', ItemTag::LowStock],
            ['Disposable Face Mask', 'INV-CM-001', 'Consumables', 240, 'pcs', 100, 'Beauty Depot PH', ItemTag::Available],
            ['Disposable Head Cover', 'INV-CM-002', 'Consumables', 180, 'pcs', 100, 'Beauty Depot PH', ItemTag::Available],
            ['Cotton Pads', 'INV-CM-003', 'Consumables', 12, 'packs', 6, 'Watsons PH', ItemTag::Available],
            ['Nitrile Gloves (M)', 'INV-CM-004', 'Consumables', 45, 'pcs', 60, 'Medical PH', ItemTag::LowStock],
            ['Brow Wax Strip', 'INV-RT-001', 'Retail Products', 34, 'pcs', 15, 'Watsons PH', ItemTag::Available],
            ['Kapis Fresh Facial Set', 'INV-RT-002', 'Retail Products', 8, 'sets', 6, 'Beauty Depot PH', ItemTag::Available],
            ['Arjud Herbal Hair Oil (Retail)', 'INV-RT-003', 'Retail Products', 0, 'pcs', 4, 'In-house', ItemTag::SoldOut],
        ];

        foreach ($items as [$name, $sku, $category, $qty, $unit, $threshold, $supplier, $tag]) {
            InventoryItem::updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'category' => $category,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'reorder_threshold' => $threshold,
                    'supplier' => $supplier,
                    'status_tag' => $tag,
                    'is_active' => true,
                ],
            );
        }

        $this->linkServices();
    }

    /**
     * Which services consume which items. Booking a service decrements these.
     */
    protected function linkServices(): void
    {
        $map = [
            'Signature Blowout & Styling' => ['INV-HC-001' => 0.05, 'INV-HC-002' => 0.05, 'INV-ST-001' => 0.05, 'INV-ST-002' => 0.02],
            'Rebonding / Straightening' => ['INV-HC-001' => 0.08, 'INV-HC-002' => 0.08, 'INV-DS-002' => 0.02],
            'Glow Manicure' => ['INV-NC-003' => 0.5, 'INV-NC-004' => 0, 'INV-CM-003' => 0.2, 'INV-DS-001' => 0.3],
            'Gelish Manicure' => ['INV-NC-001' => 0.1, 'INV-NC-002' => 0.4, 'INV-NC-003' => 0.5, 'INV-CM-003' => 0.2, 'INV-DS-001' => 0.3],
            'Classic Pedicure' => ['INV-CM-001' => 1, 'INV-CM-003' => 0.2, 'INV-DS-001' => 0.3],
            'Spa Pedicure' => ['INV-CM-001' => 1, 'INV-MA-001' => 8, 'INV-MA-002' => 6, 'INV-CM-003' => 0.2, 'INV-DS-001' => 0.3],
            'Classic Eyelash Extensions' => ['INV-LB-001' => 0.01, 'INV-LB-002' => 0.3, 'INV-CM-004' => 1, 'INV-DS-001' => 0.2],
            'Volume Lash Extensions' => ['INV-LB-001' => 0.01, 'INV-LB-002' => 0.3, 'INV-CM-004' => 1, 'INV-DS-001' => 0.2],
            'Brow Lamination & Tint' => ['INV-LB-003' => 0.05, 'INV-CM-003' => 0.2, 'INV-DS-001' => 0.2],
            'Hydrating Facial' => ['INV-SK-002' => 0.2, 'INV-CM-001' => 1, 'INV-DS-001' => 0.2],
            'Brightening Facial' => ['INV-SK-001' => 0.1, 'INV-SK-002' => 0.2, 'INV-CM-001' => 1, 'INV-DS-001' => 0.2],
            'Relaxing Massage (60 min)' => ['INV-MA-001' => 10, 'INV-MA-002' => 8, 'INV-MA-003' => 2],
            'Aromatherapy Massage (90 min)' => ['INV-MA-001' => 15, 'INV-MA-002' => 12, 'INV-MA-003' => 3, 'INV-MA-004' => 0.2],
            'Herbal Steam & Body Scrub' => ['INV-MA-004' => 0.2, 'INV-MA-001' => 8, 'INV-DS-001' => 0.3],
            'Brazilian Waxing' => ['INV-CM-003' => 0.3, 'INV-DS-001' => 0.3],
            'Threading' => ['INV-CM-003' => 0.2, 'INV-DS-001' => 0.2],
            'Bridal Glow Package' => ['INV-HC-001' => 0.1, 'INV-NC-001' => 0.1, 'INV-LB-001' => 0.01, 'INV-CM-001' => 2],
        ];

        foreach ($map as $serviceName => $skus) {
            $service = Service::where('name', $serviceName)->first();

            if (! $service) {
                continue;
            }

            $sync = [];

            foreach ($skus as $sku => $perService) {
                $item = InventoryItem::where('sku', $sku)->first();

                if ($item && $perService > 0) {
                    $sync[$item->id] = ['quantity_per_service' => $perService];
                }
            }

            $service->inventoryItems()->sync($sync);
        }
    }
}
