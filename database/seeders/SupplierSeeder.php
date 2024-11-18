<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $crm_suppliers = array(
            array(
                "name" => "Microsoft",
                "slug" => "microsoft",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => NULL,
                "updated_at" => NULL,
            ),
            array(
                "name" => "Laravel Forge",
                "slug" => "forge",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => NULL,
                "updated_at" => NULL,
            ),
            array(
                "name" => "GitHub",
                "slug" => "github",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => NULL,
                "updated_at" => NULL,
            ),
            array(
                "name" => "Qonto",
                "slug" => "qonto",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => NULL,
                "updated_at" => NULL,
            ),
            array(
                "name" => "Google Cloud",
                "slug" => "google-cloud",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => "2024-11-08 14:16:32",
                "updated_at" => "2024-11-08 14:16:32",
            ),
            array(
                "name" => "URSSAF",
                "slug" => "urssaf",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => "2024-11-08 14:17:33",
                "updated_at" => "2024-11-08 14:17:33",
            ),
            array(
                "name" => "Mailgun",
                "slug" => "mailgun",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => "2024-11-08 14:17:46",
                "updated_at" => "2024-11-08 14:17:46",
            ),
            array(
                "name" => "Amazon Cloud",
                "slug" => "amazon-cloud",
                "email" => NULL,
                "incoming_email" => NULL,
                "incoming_email_title_filter" => NULL,
                "phone" => NULL,
                "address" => NULL,
                "country" => NULL,
                "city" => NULL,
                "memo" => NULL,
                "created_at" => "2024-11-08 14:18:08",
                "updated_at" => "2024-11-08 14:18:08",
            ),
        );



        DB::table('crm_suppliers')->insert($crm_suppliers);
    }
}
