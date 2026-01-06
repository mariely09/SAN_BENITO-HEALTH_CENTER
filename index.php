<?php
require_once 'config/session.php';
require_once 'config/functions.php';

// Clear any existing session when user visits index page
// This ensures users must login again even if they were previously logged in
if (isLoggedIn()) {
    session_destroy();
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>San Benito Health Center</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/index.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" id="navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#home">
                <img src="assets/img/san-benito-logo.png" alt="San Benito Logo" class="navbar-logo me-2">
                San Benito Health Center
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">
                            <i class="fas fa-info-circle me-1"></i>About
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">
                            <i class="fas fa-stethoscope me-1"></i>Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#goals">
                            <i class="fas fa-bullseye me-1"></i>Goals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#news">
                            <i class="fas fa-newspaper me-1"></i>News
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Sign In
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus me-1"></i>Sign Up
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="hero-content" data-aos="fade-up">
            <h1 class="hero-title">San Benito Health Center</h1>
            <p class="hero-subtitle">Barangay Health Inventory & Vaccination Management System</p>
            <p class="hero-description">
                Serving the community of San Benito, Victoria, Laguna with comprehensive healthcare management and
                vaccination services for a healthier tomorrow.
            </p>
            <div class="hero-buttons">
                <a href="register.php" class="btn-hero">
                    <i class="fas fa-sign-in-alt me-2"></i>Get Started
                </a>
                <a href="#about" class="btn-hero btn-outline">
                    <i class="fas fa-info-circle me-2"></i>Learn More
                </a>
            </div>
        </div>
    </section>
    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">About Barangay San Benito</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4>Our Location</h4>
                        <p>Located in the heart of Purok 3 San Benito, Victoria Laguna. Barangay San Benito is a
                            thriving community committed to providing quality healthcare services to all residents.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Our Community</h4>
                        <p>We serve a diverse population of families, children, and elderly residents, ensuring that
                            healthcare is accessible and comprehensive for everyone in our barangay.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Our Mission</h4>
                        <p>To provide quality healthcare services, maintain accurate health records, and ensure proper
                            vaccination coverage for all residents of Barangay San Benito.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Our Services</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <h4 class="service-title">Vaccination Programs</h4>
                        <p>Comprehensive immunization services for infants, children, and adults following the national
                            vaccination schedule.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-pills"></i>
                        </div>
                        <h4 class="service-title">Medicine Inventory</h4>
                        <p>Proper storage and distribution of essential medicines with real-time inventory tracking and
                            expiry monitoring.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-baby"></i>
                        </div>
                        <h4 class="service-title">Child Health Records</h4>
                        <p>Comprehensive tracking of child health information, growth monitoring, and vaccination
                            history management.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4 class="service-title">Health Appointments</h4>
                        <p>Easy scheduling system for health check-ups, vaccinations, and consultations with our
                            healthcare workers.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Goals Section -->
    <section id="goals" class="goals-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Our Goals & Objectives</h2>
            <div class="row">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="goal-item">
                        <div class="goal-number">1</div>
                        <div>
                            <h5>Improve Healthcare Access</h5>
                            <p>Ensure all residents have easy access to essential healthcare services and medications
                                through our digital management system.</p>
                        </div>
                    </div>
                    <div class="goal-item">
                        <div class="goal-number">2</div>
                        <div>
                            <h5>Enhance Vaccination Coverage</h5>
                            <p>Achieve 100% vaccination coverage for all eligible children and maintain accurate
                                immunization records.</p>
                        </div>
                    </div>
                    <div class="goal-item">
                        <div class="goal-number">3</div>
                        <div>
                            <h5>Streamline Health Records</h5>
                            <p>Digitize and organize all health records for better tracking, reporting, and healthcare
                                delivery.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="goal-item">
                        <div class="goal-number">4</div>
                        <div>
                            <h5>Prevent Medicine Wastage</h5>
                            <p>Implement efficient inventory management to reduce medicine expiry and ensure optimal
                                stock levels.</p>
                        </div>
                    </div>
                    <div class="goal-item">
                        <div class="goal-number">5</div>
                        <div>
                            <h5>Community Health Education</h5>
                            <p>Promote health awareness and preventive care through community programs and health
                                education initiatives.</p>
                        </div>
                    </div>
                    <div class="goal-item">
                        <div class="goal-number">6</div>
                        <div>
                            <h5>Data-Driven Decisions</h5>
                            <p>Use health data and analytics to make informed decisions for better healthcare planning
                                and resource allocation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Health News Section -->
    <section id="news" class="news-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Latest Health News</h2>
            <p class="text-center mb-5" data-aos="fade-up">Stay informed with the latest health updates and medical breakthroughs</p>
            
            <!-- Loading Spinner -->
            <div id="newsLoading" class="news-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Fetching latest health news...</p>
            </div>

            <!-- News Container -->
            <div id="newsContainer" class="row g-3 g-md-4" style="display: none;">
                <!-- News cards will be dynamically inserted here -->
            </div>

            <!-- Error/No News Message -->
            <div id="newsError" class="news-error" style="display: none;">
                <i class="fas fa-newspaper fa-3x mb-3"></i>
                <p>No current health news available at the moment.</p>
                <p class="text-muted">Please check back later for updates.</p>
            </div>
        </div>
    </section>

    <!-- DOH Updates Floating Widget -->
    <div id="dohWidget" class="doh-widget">
        <div class="doh-widget-header">
            <div class="doh-widget-title">
                <i class="fas fa-hospital-symbol me-2"></i>
                <span>DOH Updates</span>
            </div>
            <button class="doh-widget-toggle" id="dohToggle">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="doh-widget-body" id="dohWidgetBody">
            <!-- Loading State -->
            <div id="dohLoading" class="doh-loading">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-2">Loading updates...</span>
            </div>
            
            <!-- Ticker Container -->
            <div id="dohTicker" class="doh-ticker" style="display: none;">
                <div class="doh-ticker-content" id="dohTickerContent">
                    <!-- Updates will be inserted here -->
                </div>
            </div>

            <!-- Navigation Dots -->
            <div id="dohDots" class="doh-dots" style="display: none;">
                <!-- Dots will be inserted here -->
            </div>

            <!-- Error State -->
            <div id="dohError" class="doh-error" style="display: none;">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span>Unable to load updates</span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5><i class="fas fa-heartbeat me-2"></i>San Benito Health Center</h5>
                    <p>Committed to providing quality healthcare services to the residents of Barangay San Benito,
                        Masapang, Victoria, Laguna.</p>
                </div>
                <div class="footer-section">
                    <h5>Contact Information</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i>Barangay San Benito, Masapang, Victoria, Laguna</p>
                    <p><i class="fas fa-phone me-2"></i>Contact your local barangay office</p>
                    <p><i class="fas fa-clock me-2"></i>Mon-Fri: 8:00 AM - 5:00 PM</p>
                </div>
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <p><a href="login.php" class="text-light text-decoration-none">System Login</a></p>
                    <p><a href="register.php" class="text-light text-decoration-none">Register Account</a></p>
                    <p><a href="#services" class="text-light text-decoration-none">Our Services</a></p>
                </div>
                <div class="footer-section">
                    <h5>Emergency</h5>
                    <p>For medical emergencies, please contact:</p>
                    <p><strong>Emergency Hotline: 911</strong></p>
                    <p><strong>Local Emergency: Contact Barangay Hall</strong></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 San Benito Health Center Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function () {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation
        window.addEventListener('load', function () {
            document.body.style.opacity = '1';
        });

        // ========================================
        // FETCH LATEST HEALTH NEWS
        // ========================================
        
        /**
         * Fetch health news from NewsAPI via PHP proxy
         * Using async/await for cleaner asynchronous code
         * Uses PHP proxy to avoid CORS issues on mobile devices
         */
        async function fetchHealthNews() {
            // Get DOM elements
            const loadingElement = document.getElementById('newsLoading');
            const containerElement = document.getElementById('newsContainer');
            const errorElement = document.getElementById('newsError');

            try {
                // Use PHP proxy to avoid CORS issues on mobile
                const API_URL = 'api/get_health_news.php';

                // Fetch data from PHP proxy
                const response = await fetch(API_URL, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                // Check if response is successful
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Parse JSON response
                const data = await response.json();

                // Hide loading spinner
                loadingElement.style.display = 'none';

                // Check for error in proxy response
                if (data.error) {
                    throw new Error(data.message || 'Failed to fetch news');
                }

                // Check if articles exist
                if (data.articles && data.articles.length > 0) {
                    // Display news articles
                    displayNews(data.articles);
                    containerElement.style.display = 'flex';
                } else {
                    // Show error message if no articles found
                    errorElement.style.display = 'block';
                }

            } catch (error) {
                // Handle any errors during fetch
                console.error('Error fetching health news:', error);
                console.error('Error details:', error.message);
                loadingElement.style.display = 'none';
                errorElement.style.display = 'block';
                
                // Update error message for better debugging
                const errorMsg = errorElement.querySelector('p');
                if (errorMsg && error.message) {
                    errorMsg.textContent = `Unable to load health news. ${error.message}`;
                }
            }
        }

        /**
         * Display news articles in card format
         * @param {Array} articles - Array of news article objects
         */
        function displayNews(articles) {
            const container = document.getElementById('newsContainer');
            container.innerHTML = ''; // Clear existing content

            console.log('Displaying news articles:', articles.length);

            // Loop through each article and create a card
            articles.forEach((article, index) => {
                // Format the published date
                const publishedDate = new Date(article.publishedAt).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                // Create news card HTML with better mobile responsiveness
                const newsCard = `
                    <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="${index * 100}">
                        <div class="news-card">
                            ${article.urlToImage ? `
                                <div class="news-image" style="background-image: url('${article.urlToImage}')"></div>
                            ` : `
                                <div class="news-image news-image-placeholder">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            `}
                            <div class="news-content">
                                <div class="news-source">
                                    <i class="fas fa-building me-1"></i>
                                    ${article.source.name || 'Unknown Source'}
                                </div>
                                <h5 class="news-title">${article.title || 'No Title Available'}</h5>
                                <p class="news-description">${article.description || 'No description available for this article.'}</p>
                                <div class="news-footer">
                                    <div class="news-date">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        ${publishedDate}
                                    </div>
                                    <a href="${article.url}" target="_blank" rel="noopener noreferrer" class="news-link">
                                        Read More <i class="fas fa-external-link-alt ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Insert card into container
                container.innerHTML += newsCard;
            });

            console.log('News cards created successfully');

            // Reinitialize AOS for new elements
            AOS.refresh();
        }

        // Fetch news when page loads
        window.addEventListener('load', function() {
            console.log('Page loaded, fetching health news...');
            fetchHealthNews();
        });

        // ========================================
        // DOH UPDATES WIDGET - TICKER/CAROUSEL
        // ========================================

        let currentTickerIndex = 0;
        let tickerInterval = null;
        let dohUpdates = [];

        /**
         * Fetch DOH health updates from NewsAPI via PHP proxy
         * Filters for Philippines health news
         */
        async function fetchDOHUpdates() {
            const loadingElement = document.getElementById('dohLoading');
            const tickerElement = document.getElementById('dohTicker');
            const errorElement = document.getElementById('dohError');
            const dotsElement = document.getElementById('dohDots');

            try {
                // Use PHP proxy to avoid CORS issues on mobile
                const API_URL = 'api/get_doh_updates.php';

                const response = await fetch(API_URL, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                // Check for error in proxy response
                if (data.error) {
                    throw new Error(data.message || 'DOH API error');
                }

                // Hide loading
                loadingElement.style.display = 'none';

                if (data.articles && data.articles.length > 0) {
                    dohUpdates = data.articles.slice(0, 5); // Get top 5 updates
                    displayDOHTicker(dohUpdates);
                    tickerElement.style.display = 'block';
                    dotsElement.style.display = 'flex';
                    startTickerAutoplay();
                } else {
                    errorElement.style.display = 'flex';
                }

            } catch (error) {
                console.error('Error fetching DOH updates:', error);
                console.error('Error details:', error.message);
                loadingElement.style.display = 'none';
                errorElement.style.display = 'flex';
                
                // Update error message for better debugging
                const errorMsg = errorElement.querySelector('span');
                if (errorMsg && error.message) {
                    errorMsg.textContent = `Unable to load updates. ${error.message}`;
                }
            }
        }

        /**
         * Display DOH updates in ticker format
         * @param {Array} updates - Array of update objects
         */
        function displayDOHTicker(updates) {
            const tickerContent = document.getElementById('dohTickerContent');
            const dotsContainer = document.getElementById('dohDots');
            
            tickerContent.innerHTML = '';
            dotsContainer.innerHTML = '';

            updates.forEach((update, index) => {
                // Format date
                const updateDate = new Date(update.publishedAt).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });

                // Create ticker item
                const tickerItem = document.createElement('div');
                tickerItem.className = `doh-ticker-item ${index === 0 ? 'active' : ''}`;
                tickerItem.innerHTML = `
                    <div class="doh-update-date">
                        <i class="fas fa-calendar-day me-1"></i>
                        ${updateDate}
                    </div>
                    <h6 class="doh-update-title">${update.title}</h6>
                    <p class="doh-update-description">${update.description || 'No description available.'}</p>
                    <a href="${update.url}" target="_blank" rel="noopener noreferrer" class="doh-update-link">
                        Read Full Update <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                `;
                tickerContent.appendChild(tickerItem);

                // Create navigation dot
                const dot = document.createElement('span');
                dot.className = `doh-dot ${index === 0 ? 'active' : ''}`;
                dot.addEventListener('click', () => goToTickerSlide(index));
                dotsContainer.appendChild(dot);
            });
        }

        /**
         * Navigate to specific ticker slide
         * @param {number} index - Slide index
         */
        function goToTickerSlide(index) {
            const items = document.querySelectorAll('.doh-ticker-item');
            const dots = document.querySelectorAll('.doh-dot');

            // Remove active class from all
            items.forEach(item => item.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));

            // Add active class to current
            items[index].classList.add('active');
            dots[index].classList.add('active');

            currentTickerIndex = index;
        }

        /**
         * Move to next ticker slide
         */
        function nextTickerSlide() {
            currentTickerIndex = (currentTickerIndex + 1) % dohUpdates.length;
            goToTickerSlide(currentTickerIndex);
        }

        /**
         * Start automatic ticker rotation
         */
        function startTickerAutoplay() {
            // Clear existing interval
            if (tickerInterval) {
                clearInterval(tickerInterval);
            }
            // Auto-advance every 5 seconds
            tickerInterval = setInterval(nextTickerSlide, 5000);
        }

        /**
         * Stop ticker autoplay
         */
        function stopTickerAutoplay() {
            if (tickerInterval) {
                clearInterval(tickerInterval);
                tickerInterval = null;
            }
        }

        /**
         * Toggle widget collapse/expand
         */
        function toggleDOHWidget() {
            const widgetBody = document.getElementById('dohWidgetBody');
            const toggleBtn = document.getElementById('dohToggle');
            const widget = document.getElementById('dohWidget');

            widget.classList.toggle('collapsed');
            
            const icon = toggleBtn.querySelector('i');
            if (widget.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-up';
                stopTickerAutoplay();
            } else {
                icon.className = 'fas fa-chevron-down';
                if (dohUpdates.length > 0) {
                    startTickerAutoplay();
                }
            }
        }

        // Initialize DOH widget
        document.getElementById('dohToggle').addEventListener('click', toggleDOHWidget);

        // Pause autoplay on hover
        document.getElementById('dohWidget').addEventListener('mouseenter', stopTickerAutoplay);
        document.getElementById('dohWidget').addEventListener('mouseleave', () => {
            const widget = document.getElementById('dohWidget');
            if (!widget.classList.contains('collapsed') && dohUpdates.length > 0) {
                startTickerAutoplay();
            }
        });

        // Fetch DOH updates when page loads
        window.addEventListener('load', fetchDOHUpdates);
    </script>
</body>

</html>