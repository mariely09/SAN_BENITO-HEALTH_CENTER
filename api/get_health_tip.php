<?php
/**
 * Health Tip API
 * Returns health and wellness tips for the dashboard
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Curated health and wellness tips
$health_tips = [
    [
        'quote' => 'Drink at least 8 glasses of water daily to stay hydrated and maintain optimal body function.',
        'author' => 'Health Guidelines',
        'category' => 'health'
    ],
    [
        'quote' => 'Get 7-9 hours of quality sleep each night to support your immune system and mental health.',
        'author' => 'Sleep Foundation',
        'category' => 'health'
    ],
    [
        'quote' => 'Eat a rainbow of fruits and vegetables daily to ensure you get a variety of essential nutrients.',
        'author' => 'Nutrition Experts',
        'category' => 'health'
    ],
    [
        'quote' => 'Take a 10-minute walk after meals to aid digestion and regulate blood sugar levels.',
        'author' => 'Wellness Advisors',
        'category' => 'health'
    ],
    [
        'quote' => 'Practice deep breathing for 5 minutes daily to reduce stress and improve mental clarity.',
        'author' => 'Mindfulness Experts',
        'category' => 'health'
    ],
    [
        'quote' => 'Wash your hands regularly with soap for at least 20 seconds to prevent the spread of germs.',
        'author' => 'CDC Guidelines',
        'category' => 'health'
    ],
    [
        'quote' => 'Limit screen time before bed to improve sleep quality and protect your eyes.',
        'author' => 'Sleep Specialists',
        'category' => 'health'
    ],
    [
        'quote' => 'Stand up and stretch every hour if you sit for long periods to prevent muscle stiffness.',
        'author' => 'Ergonomic Experts',
        'category' => 'health'
    ],
    [
        'quote' => 'Include protein in every meal to support muscle health and keep you feeling full longer.',
        'author' => 'Dietitians',
        'category' => 'health'
    ],
    [
        'quote' => 'Spend time outdoors daily to boost vitamin D levels and improve your mood.',
        'author' => 'Health Professionals',
        'category' => 'health'
    ],
    [
        'quote' => 'Limit processed foods and added sugars to reduce inflammation and support overall health.',
        'author' => 'Nutritionists',
        'category' => 'health'
    ],
    [
        'quote' => 'Practice good posture to prevent back pain and improve breathing efficiency.',
        'author' => 'Physical Therapists',
        'category' => 'health'
    ],
    [
        'quote' => 'Stay socially connected with friends and family to support mental and emotional wellbeing.',
        'author' => 'Mental Health Experts',
        'category' => 'health'
    ],
    [
        'quote' => 'Exercise for at least 30 minutes most days of the week to strengthen your heart and bones.',
        'author' => 'Fitness Guidelines',
        'category' => 'health'
    ],
    [
        'quote' => 'Keep your vaccinations up to date to protect yourself and your community from preventable diseases.',
        'author' => 'Public Health Officials',
        'category' => 'health'
    ],
    [
        'quote' => 'Reduce salt intake to maintain healthy blood pressure and support heart health.',
        'author' => 'Cardiologists',
        'category' => 'health'
    ],
    [
        'quote' => 'Take breaks from work to prevent burnout and maintain productivity and creativity.',
        'author' => 'Workplace Wellness',
        'category' => 'health'
    ],
    [
        'quote' => 'Chew your food slowly and mindfully to improve digestion and prevent overeating.',
        'author' => 'Digestive Health Experts',
        'category' => 'health'
    ],
    [
        'quote' => 'Maintain a healthy weight through balanced nutrition and regular physical activity.',
        'author' => 'Health Organizations',
        'category' => 'health'
    ],
    [
        'quote' => 'Schedule regular health check-ups to catch potential issues early and stay on top of your wellness.',
        'author' => 'Healthcare Providers',
        'category' => 'health'
    ]
];

// Return a random health tip
$random_tip = $health_tips[array_rand($health_tips)];
echo json_encode([$random_tip]);
?>
