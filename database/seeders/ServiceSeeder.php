<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $entry) {
            $service = Service::updateOrCreate(
                ['slug' => Str::slug($entry['name'])],
                [
                    'name' => $entry['name'],
                    'category' => $entry['category'],
                    'price' => $entry['price'],
                    'duration_minutes' => $entry['duration'],
                    'description' => $entry['description'],
                    'photo_path' => $entry['photo'] ?? null,
                    'is_active' => true,
                    'is_featured' => $entry['featured'] ?? false,
                ],
            );

            foreach ($entry['variants'] ?? [] as $variant) {
                $service->variants()->updateOrCreate(
                    ['name' => $variant['name']],
                    [
                        'price' => $variant['price'],
                        'duration_minutes' => $variant['duration'] ?? null,
                        'is_default' => $variant['default'] ?? false,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function catalogue(): array
    {
        return [
            [
                'name' => 'Signature Blowout & Styling',
                'category' => 'Hair Styling',
                'price' => 450,
                'duration' => 60,
                'description' => 'A wash, blow-dry and finish tailored to your hair type and event.',
                'featured' => true,
                'photo' => 'photos/service-blowout.jpg',
                'variants' => [
                    ['name' => 'Short Hair', 'price' => 400, 'default' => true],
                    ['name' => 'Medium Hair', 'price' => 450],
                    ['name' => 'Long Hair', 'price' => 550, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Rebonding / Straightening',
                'category' => 'Hair Care',
                'price' => 2800,
                'duration' => 180,
                'description' => 'Chemical straightening for a smooth, manageable finish. Includes post-treatment care.',
                'variants' => [
                    ['name' => 'Short Hair', 'price' => 2800, 'default' => true],
                    ['name' => 'Long Hair', 'price' => 3800, 'duration' => 240],
                ],
            ],
            [
                'name' => 'Color & Highlights',
                'category' => 'Hair Care',
                'price' => 2200,
                'duration' => 150,
                'description' => 'Full colour change or highlight placement with gloss and blow-dry.',
                'variants' => [
                    ['name' => 'Global Color', 'price' => 2200, 'default' => true],
                    ['name' => 'Highlights', 'price' => 3200, 'duration' => 210],
                    ['name' => 'Balayage', 'price' => 4200, 'duration' => 240],
                ],
            ],
            [
                'name' => 'Glow Manicure',
                'category' => 'Nail Care',
                'price' => 350,
                'duration' => 45,
                'description' => 'Cleanse, shape, cuticle care and a high-shine finish.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Classic', 'price' => 350, 'default' => true],
                    ['name' => 'With Nail Art', 'price' => 450, 'duration' => 60],
                ],
            ],
            [
                'name' => 'Gelish Manicure',
                'category' => 'Nail Care',
                'price' => 600,
                'duration' => 75,
                'description' => 'Long-wear gel colour with a strengthening base and top coat.',
                'featured' => true,
                'photo' => 'photos/service-manicure.jpg',
                'variants' => [
                    ['name' => 'Gelish Manicure', 'price' => 600, 'default' => true],
                    ['name' => 'Gelish with Nail Art', 'price' => 750, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Classic Pedicure',
                'category' => 'Nail Care',
                'price' => 400,
                'duration' => 60,
                'description' => 'Foot soak, exfoliation, nail shaping and polish.',
            ],
            [
                'name' => 'Spa Pedicure',
                'category' => 'Nail Care',
                'price' => 650,
                'duration' => 90,
                'description' => 'Aromatic soak, sugar scrub, mask, massage and polish.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Spa Pedicure', 'price' => 650, 'default' => true],
                    ['name' => 'Spa Pedicure + Paraffin', 'price' => 850, 'duration' => 105],
                ],
            ],
            [
                'name' => 'Classic Eyelash Extensions',
                'category' => 'Lash & Brow',
                'price' => 1800,
                'duration' => 120,
                'description' => 'Hand-applied individual lashes for a natural, defined look.',
                'variants' => [
                    ['name' => 'Classic Set', 'price' => 1800, 'default' => true],
                    ['name' => 'Refill (2-3 weeks)', 'price' => 1200, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Volume Lash Extensions',
                'category' => 'Lash & Brow',
                'price' => 2200,
                'duration' => 150,
                'description' => 'Denser, fuller lashes mapped to your eye shape.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Volume Set', 'price' => 2200, 'default' => true],
                    ['name' => 'Volume Refill', 'price' => 1500, 'duration' => 105],
                ],
            ],
            [
                'name' => 'Brow Lamination & Tint',
                'category' => 'Lash & Brow',
                'price' => 950,
                'duration' => 75,
                'description' => 'Lifted, brushed-up brows with a tint to suit your hair colour.',
            ],
            [
                'name' => 'Hydrating Facial',
                'category' => 'Facial',
                'price' => 1100,
                'duration' => 75,
                'description' => 'Gentle cleanse, exfoliation, hydrating mask and massage.',
            ],
            [
                'name' => 'Brightening Facial',
                'category' => 'Facial',
                'price' => 1500,
                'duration' => 90,
                'description' => 'Targeted treatment for dull or uneven skin tone.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Brightening Facial', 'price' => 1500, 'default' => true],
                    ['name' => 'Brightening + Peel', 'price' => 2000, 'duration' => 105],
                ],
            ],
            [
                'name' => 'Relaxing Massage (60 min)',
                'category' => 'Massage & Spa',
                'price' => 900,
                'duration' => 60,
                'description' => 'A soothing full-body massage to unwind tension.',
                'variants' => [
                    ['name' => '60 minutes', 'price' => 900, 'default' => true],
                    ['name' => '90 minutes', 'price' => 1250, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Aromatherapy Massage (90 min)',
                'category' => 'Massage & Spa',
                'price' => 1500,
                'duration' => 90,
                'description' => 'Essential-oil massage tailored for deep relaxation.',
                'photo' => 'photos/service-massage.jpg',
            ],
            [
                'name' => 'Herbal Steam & Body Scrub',
                'category' => 'Massage & Spa',
                'price' => 1200,
                'duration' => 90,
                'description' => 'Warm herbal steam followed by a full-body exfoliation.',
            ],
            [
                'name' => 'Brazilian Waxing',
                'category' => 'Waxing & Threading',
                'price' => 650,
                'duration' => 45,
                'description' => 'Professional waxing with sensitive-skin formula.',
            ],
            [
                'name' => 'Threading',
                'category' => 'Waxing & Threading',
                'price' => 200,
                'duration' => 30,
                'description' => 'Precise eyebrow and facial threading.',
            ],
            [
                'name' => 'Occasion Makeup',
                'category' => 'Makeup',
                'price' => 1800,
                'duration' => 90,
                'description' => 'Full-face makeup for events, photos and celebrations.',
            ],
            [
                'name' => 'Bridal Glow Package',
                'category' => 'Packages',
                'price' => 12500,
                'duration' => 300,
                'description' => 'A full pre-wedding journey: facial, manicure, lashes, hair and makeup trial.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Bridal Glow', 'price' => 12500, 'default' => true],
                    ['name' => 'Bridal Glow + Extensions', 'price' => 16800, 'duration' => 420],
                ],
            ],
        ];
    }
}
