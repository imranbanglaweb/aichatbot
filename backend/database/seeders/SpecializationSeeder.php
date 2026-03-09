<?php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    /**
     * Seed specializations dynamically
     */
    public function run(): void
    {
        $specializations = [
            [
                'name' => 'Cardiologist',
                'slug' => 'cardiologist',
                'description' => 'Heart and cardiovascular system specialist',
                'common_symptoms' => ['chest pain', 'shortness of breath', 'palpitations', 'high blood pressure', 'heart disease']
            ],
            [
                'name' => 'Dermatologist',
                'slug' => 'dermatologist',
                'description' => 'Skin, hair, and nails specialist',
                'common_symptoms' => ['rash', 'acne', 'itching', 'skin discoloration', 'skin disease']
            ],
            [
                'name' => 'Neurologist',
                'slug' => 'neurologist',
                'description' => 'Brain and nervous system specialist',
                'common_symptoms' => ['headache', 'dizziness', 'numbness', 'seizures', 'migraine']
            ],
            [
                'name' => 'Pediatrician',
                'slug' => 'pediatrician',
                'description' => "Children's health specialist",
                'common_symptoms' => ['fever', 'cough', 'growth concerns', 'vaccination', 'child health']
            ],
            [
                'name' => 'Orthopedist',
                'slug' => 'orthopedist',
                'description' => 'Bones, joints, and muscles specialist',
                'common_symptoms' => ['joint pain', 'fracture', 'back pain', 'stiffness', 'bone problems']
            ],
            [
                'name' => 'General Physician',
                'slug' => 'general-physician',
                'description' => 'Primary care physician',
                'common_symptoms' => ['fever', 'cold', 'fatigue', 'general illness', 'routine checkup']
            ],
            [
                'name' => 'Psychiatrist',
                'slug' => 'psychiatrist',
                'description' => 'Mental health specialist',
                'common_symptoms' => ['anxiety', 'depression', 'stress', 'sleep problems', 'mental health']
            ],
            [
                'name' => 'Ophthalmologist',
                'slug' => 'ophthalmologist',
                'description' => 'Eye specialist',
                'common_symptoms' => ['blurred vision', 'eye pain', 'red eyes', 'dry eyes', 'eye problems']
            ],
            [
                'name' => 'ENT Specialist',
                'slug' => 'ent',
                'description' => 'Ear, nose, and throat specialist',
                'common_symptoms' => ['sore throat', 'ear pain', 'hearing loss', 'sinus issues', 'nose problems']
            ],
            [
                'name' => 'Gynecologist',
                'slug' => 'gynecologist',
                'description' => "Women's health specialist",
                'common_symptoms' => ['irregular periods', 'pelvic pain', 'pregnancy care', 'menopause', 'women health']
            ],
            [
                'name' => 'Dentist',
                'slug' => 'dentist',
                'description' => 'Dental and oral health specialist',
                'common_symptoms' => ['tooth pain', 'toothache', 'gum pain', 'cavities', 'dental checkup']
            ],
            [
                'name' => 'Gastroenterologist',
                'slug' => 'gastroenterologist',
                'description' => 'Digestive system specialist',
                'common_symptoms' => ['stomach pain', 'acid reflux', 'digestive issues', 'liver problems', 'bowel issues']
            ],
            [
                'name' => 'Nephrologist',
                'slug' => 'nephrologist',
                'description' => 'Kidney specialist',
                'common_symptoms' => ['kidney problems', 'urinary issues', 'swelling', 'high blood pressure', 'kidney stones']
            ],
            [
                'name' => 'Pulmonologist',
                'slug' => 'pulmonologist',
                'description' => 'Lung and respiratory specialist',
                'common_symptoms' => ['breathing problems', 'asthma', 'cough', 'lung issues', 'respiratory']
            ],
            [
                'name' => 'Urologist',
                'slug' => 'urologist',
                'description' => 'Urinary system specialist',
                'common_symptoms' => ['urinary problems', 'kidney stones', 'prostate issues', 'bladder problems']
            ],
        ];

        foreach ($specializations as $spec) {
            $existing = Specialization::where('slug', $spec['slug'])->first();
            if (!$existing) {
                Specialization::create($spec);
                $this->command->info("✅ Created: {$spec['name']}");
            } else {
                $existing->update($spec);
                $this->command->info("🔄 Updated: {$spec['name']}");
            }
        }
    }
}
