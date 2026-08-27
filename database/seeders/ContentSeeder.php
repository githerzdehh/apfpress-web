<?php

namespace Database\Seeders;

use App\Models\IntegrationSetting;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about',
                'title' => 'Publishing bold ideas with purpose',
                'content_blocks' => [
                    ['type' => 'lead', 'text' => 'APF Press is an independent Canadian academic publisher serving university and college faculty, students, and curious readers.'],
                    ['type' => 'heading', 'text' => 'Scholarship that makes room for change'],
                    ['type' => 'paragraph', 'text' => 'We publish rigorous work on racialized communities, community concerns, social justice, and human rights. Our authors are academics, activists, artists, and professional practitioners who encourage readers to be curious, committed, and courageous in challenging inequity.'],
                    ['type' => 'paragraph', 'text' => 'We are especially committed to Canadian scholars and to authors who are under-represented or whose work is too often dismissed by mainstream and traditional publishing venues.'],
                ],
                'status' => 'published',
                'seo_title' => 'About APF Press | Independent Academic Publisher',
                'seo_description' => 'Meet APF Press, an independent Canadian publisher advancing critical scholarship, social justice, human rights, and under-represented voices.',
            ],
            [
                'slug' => 'publish-with-us',
                'title' => 'Publish with APF Press',
                'content_blocks' => [
                    ['type' => 'lead', 'text' => 'Have a manuscript that challenges conventions, opens difficult conversations, or serves a community overlooked by mainstream publishing? We would like to hear from you.'],
                    ['type' => 'heading', 'text' => 'What to send'],
                    ['type' => 'paragraph', 'text' => 'Share a concise abstract, the intended readership, your current manuscript status, and a brief author biography. Our editorial team will review the fit before requesting a complete manuscript.'],
                    ['type' => 'heading', 'text' => 'What happens next'],
                    ['type' => 'paragraph', 'text' => 'Suitable proposals enter an editorial review focused on scholarly quality, contribution, clarity, and alignment with APF Press values. Submission does not guarantee publication.'],
                ],
                'status' => 'published',
                'seo_title' => 'Publish With Us | APF Press',
                'seo_description' => 'Submit an academic manuscript or book proposal to APF Press, a Canadian publisher committed to critical and under-represented scholarship.',
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy policy',
                'content_blocks' => [['type' => 'notice', 'text' => 'This policy is a launch draft and must be reviewed by APF Press before publication.']],
                'status' => 'draft',
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of service',
                'content_blocks' => [['type' => 'notice', 'text' => 'These terms are a launch draft and must be reviewed by APF Press before publication.']],
                'status' => 'draft',
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Refund policy',
                'content_blocks' => [['type' => 'notice', 'text' => 'This policy is a launch draft and must be reviewed by APF Press before publication.']],
                'status' => 'draft',
            ],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(['slug' => $page['slug']], $page + ['published_at' => $page['status'] === 'published' ? now() : null]);
        }

        $board = [
            ['Brad Chilton', 'Public Administration Program, Department of Political Science, University of Texas at El Paso, USA.'],
            ['Wesley Crichlow', 'Department of Criminology and Justice, Ontario Tech University, Canada.'],
            ['Thomas Fleming', 'Criminology and Contemporary Studies, Wilfrid Laurier University, Canada.'],
            ['James F. Hodgson', 'Averett University, Virginia, USA.'],
            ['Morris Jenkins', 'Southwestern Law School, California, USA.'],
            ['Stephen Muzzatti', 'Department of Sociology, Toronto Metropolitan University, Canada.'],
            ['Catherine Orban', 'Department of Criminal Justice, Marygrove College, USA.'],
            ['Jerry Elarick Persaud', 'Department of Ethnic Studies, State University of New York at New Paltz, USA.'],
            ['Giorgos Skoulas', 'Department of International and European Studies, University of Macedonia, Greece.'],
            ['Ron Stansfield', 'Department of Sociology and Anthropology, University of Guelph, Canada.'],
        ];
        foreach ($board as $position => [$name, $affiliation]) {
            DB::table('editorial_board_members')->updateOrInsert(
                ['name' => $name],
                ['affiliation' => $affiliation, 'position' => $position, 'active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        foreach (['stripe', 'paypal'] as $provider) {
            IntegrationSetting::query()->firstOrCreate(['provider' => $provider], [
                'environment' => 'sandbox', 'enabled' => false, 'health_status' => 'untested',
            ]);
        }

        $canadaZoneId = DB::table('shipping_zones')->updateOrInsert(
            ['name' => 'Canada'],
            ['country' => 'CA', 'active' => true, 'priority' => 10, 'updated_at' => now(), 'created_at' => now()],
        );
        $canadaZoneId = DB::table('shipping_zones')->where('name', 'Canada')->value('id');
        DB::table('shipping_rules')->updateOrInsert(
            ['shipping_zone_id' => $canadaZoneId, 'name' => 'Standard shipping'],
            ['rate_amount' => 1200, 'free_above_amount' => 7500, 'active' => true, 'updated_at' => now(), 'created_at' => now()],
        );

        $usZoneId = DB::table('shipping_zones')->updateOrInsert(
            ['name' => 'United States'],
            ['country' => 'US', 'active' => true, 'priority' => 20, 'updated_at' => now(), 'created_at' => now()],
        );
        $usZoneId = DB::table('shipping_zones')->where('name', 'United States')->value('id');
        DB::table('shipping_rules')->updateOrInsert(
            ['shipping_zone_id' => $usZoneId, 'name' => 'US standard shipping'],
            ['rate_amount' => 1800, 'free_above_amount' => 10000, 'active' => true, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
