<?php

namespace Database\Seeders;

use App\Models\BedCategory;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Emergency Medicine', 'description' => '24/7 emergency assessment and stabilization.'],
            ['name' => 'Internal Medicine', 'description' => 'Adult medical admissions and specialist consultation.'],
            ['name' => 'Pediatrics', 'description' => 'Care for infants, children, and adolescents.'],
            ['name' => 'Obstetrics and Gynecology', 'description' => 'Maternal care, delivery, and gynecologic services.'],
            ['name' => 'General Surgery', 'description' => 'Elective and emergency surgical care.'],
            ['name' => 'Orthopedics', 'description' => 'Bone, joint, and trauma surgery.'],
            ['name' => 'Cardiology', 'description' => 'Cardiac assessment, monitoring, and intervention.'],
            ['name' => 'Neurology', 'description' => 'Neurologic assessment and stroke care.'],
            ['name' => 'Oncology', 'description' => 'Cancer diagnosis, chemotherapy, and supportive care.'],
            ['name' => 'Dialysis', 'description' => 'Hemodialysis and renal replacement services.'],
            ['name' => 'Burn Care', 'description' => 'Specialized burn assessment and wound care.'],
            ['name' => 'Neonatal Intensive Care', 'description' => 'Level II–III neonatal intensive care.'],
            ['name' => 'Trauma Care', 'description' => 'Coordinated trauma resuscitation and surgery.'],
            ['name' => 'Infectious Disease', 'description' => 'Isolation and treatment of communicable disease.'],
            ['name' => 'Psychiatry', 'description' => 'Acute mental health assessment and stabilization.'],
            ['name' => 'Pulmonology', 'description' => 'Respiratory medicine and critical airway support.'],
        ];

        foreach ($services as $index => $service) {
            Service::query()->updateOrCreate(
                ['slug' => Str::slug($service['name'])],
                [...$service, 'active' => true]
            );
        }

        $categories = [
            ['name' => 'Emergency Room', 'slug' => 'emergency-room', 'is_er' => true, 'sort_order' => 1, 'description' => 'Resuscitation and emergency observation beds.'],
            ['name' => 'Intensive Care', 'slug' => 'intensive-care', 'is_er' => false, 'sort_order' => 2, 'description' => 'Critical care beds with continuous monitoring.'],
            ['name' => 'Neonatal ICU', 'slug' => 'neonatal-icu', 'is_er' => false, 'sort_order' => 3, 'description' => 'Neonatal intensive care incubators and warmers.'],
            ['name' => 'Medical Ward', 'slug' => 'medical-ward', 'is_er' => false, 'sort_order' => 4, 'description' => 'General adult medical inpatient beds.'],
            ['name' => 'Surgical Ward', 'slug' => 'surgical-ward', 'is_er' => false, 'sort_order' => 5, 'description' => 'Post-operative and surgical inpatient beds.'],
            ['name' => 'Pediatric Ward', 'slug' => 'pediatric-ward', 'is_er' => false, 'sort_order' => 6, 'description' => 'Inpatient beds for children.'],
            ['name' => 'Obstetric Ward', 'slug' => 'obstetric-ward', 'is_er' => false, 'sort_order' => 7, 'description' => 'Labor, delivery, and postpartum beds.'],
            ['name' => 'Isolation Room', 'slug' => 'isolation-room', 'is_er' => false, 'sort_order' => 8, 'description' => 'Negative-pressure and infectious disease isolation beds.'],
            ['name' => 'Burn Unit', 'slug' => 'burn-unit', 'is_er' => false, 'sort_order' => 9, 'description' => 'Dedicated burn resuscitation and wound care beds.'],
            ['name' => 'Dialysis Chair', 'slug' => 'dialysis-chair', 'is_er' => false, 'sort_order' => 10, 'description' => 'Hemodialysis treatment stations.'],
        ];

        foreach ($categories as $category) {
            BedCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'active' => true]
            );
        }
    }
}
