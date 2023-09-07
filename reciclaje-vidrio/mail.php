<!DOCTYPE html>
<!--
Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
Click nbfs://nbhost/SystemFileSystem/Templates/Other/html.html to edit this template
-->
<html lang="en">
    <head>
        <title>Solar Energy</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
        <link href="font-awesome/css/all.min.css" rel="stylesheet" type="text/css"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
        <link href="css/style.css" rel="stylesheet" type="text/css"/>
        <link href="css/mobyle.css" rel="stylesheet" type="text/css"/>
        <link href="css/slick.css" rel="stylesheet" type="text/css"/>
        <link href="css/slick-theme.css" rel="stylesheet" type="text/css"/>
        <link href="css/animate.min.css" rel="stylesheet" type="text/css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>


        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700">
        <link href="https://fonts.googleapis.com/css2?family=Ephesis&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Playball&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet">
    </head>
    <body>
        <!-- Topbar Start -->
        <div class="topbar">
            <div class="container-fluid">
                <div class="container">
                    <div class="row">
                        <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                            <div class="topbar-left">
                                <a><i class="fa fa-phone-alt"></i>+012-345-6789</a>
                                <span class="text-body">|</span>
                                <a><i class="fa fa-envelope mr-2"></i>solarenergy@example.com</a>
                            </div>
                        </div>
                        <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                            <div class="topbar-right">
                                <a class="px-3" href="">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a class="px-3" href="">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a class="px-3" href="">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a class="px-3" href="">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a class="pl-3" href="">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>    
            </div>    
        </div>
        <!-- Topbar End -->

        <!-- Navbar Start -->
        <div class="container-fluid position-relative  nav-bar">
            <div class="position-relative px-lg-5" style="z-index: 9;">
                <nav class="navbar navbar-expand-lg bg-secondary navbar-dark py-3 py-lg-0 pl-3 pl-lg-5">
                    <div class="container">
                        <a href="index.html" class="navbar-brand">
                            <h2>Solar Energy</h2>
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarCollapse">
                            <ul class="navbar-nav mx-auto py-0">
                                <li><a href="index.html" class="nav-item nav-link active">Home</a></li>
                                <li><a href="#about" class="nav-item nav-link">About</a></li>
                                <li><a href="#products" class="nav-item nav-link">Products</a></li>
                                <li><a href="#project" class="nav-item nav-link">Project</a></li>
                                <li><a href="#team" class="nav-item nav-link">Our Team</a></li>                           
                                <li><a href="#contact" class="nav-item nav-link">Contact</a></li>
                                <li><a href="#order" class="nav-item nav-link primary-btn order text-uppercase slideInLeft">Order Now</a></li>        
                            </ul>
                        </div>      
                    </div>    
                </nav>
            </div>
        </div>
        <!-- Navbar End -->
		
		<!-- Mobile Start -->
        <div id="nav">
            <div class="container-fluid">
                <a href="index.html" class="navbar-brand">
					<h2>Solar Energy</h2>
				</a>

                <nav id="nav-menu-container">
                    <ul class="nav-menu">
                        <li class="menu-active"><a href="#header">Home</a></li>
                        <li><a class="mob-memu" href="#about">About</a></li>
                        <li><a class="mob-memu" href="#products">Products</a></li>
                        <li><a class="mob-memu" href="#project">Project</a></li>
                        <li><a class="mob-memu" href="#team">Our Team</a></li>
                        <li><a class="mob-memu" href="#contact">Contact</a></li>
                        <li><a class="mob-memu" href="#order">Order Now</a></li>
                    </ul>
                </nav>
            </div>
        </div>
        <!-- Mobile End -->

        <!-- Main Slider Start -->
        <div class="ful-slider" id="header">
            <div class="container-fluid">
                <div id="carouselExampleControls" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="image/slider/slider1.png" class="d-block w-100" alt="Ice Cream">
                        </div>
                        <div class="carousel-item">
                            <img src="image/slider/slider2.png" class="d-block w-100" alt="Ice Cream">
                        </div>
                        <div class="carousel-item">
                            <img src="image/slider/slider3.png" class="d-block w-100" alt="Cake Shop">
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControls" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControls" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        </div>
        <!-- Main Slider End -->
        
        <!-- About Start -->
        <div class="container-xxl py-5" id="about">
            <div class="container about">
                <div class="row">
                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="img-about-top">
                            <div class="position-relative overflow-hidden ps-5 pt-5 h-100 us-item" style="min-height: 400px;">
                                <img class="position-absolute w-100 h-100" src="image/slider/about.jpg" alt="Why Choose Us" style="object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-white pe-3 pb-3" style="width: 200px; height: 200px;">
                                    <div class="d-flex flex-column justify-content-center text-center bg-primary bg-primary-us h-100 p-3">
                                        <h1 class="text-white">25</h1>
                                        <h2 class="text-white">Years</h2>
                                        <h5 class="text-white mb-0">Experience</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.5s">
                        <div class="p-lg-5 pe-lg-0">
                            <h6 class="text-primary subheading">About Us</h6>
                            <h2>25+ Years Experience In Solar & Renewable Energy Industry</h2>
                            <p>
                                Tempor erat elitr rebum at clita. Diam dolor diam ipsum sit. Aliqu diam amet diam et eos. 
                                Clita erat ipsum et lorem et sit, sed stet lorem sit clita duo justo erat amet.
                            </p>
                            <div class="row">
                                <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <i class="fa fa-check text-primary me-2"></i>Diam dolor diam ipsum
                                </div>
                                <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <i class="fa fa-check text-primary me-2"></i>Aliqu diam amet diam et eos
                                </div>
                                <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <i class="fa fa-check text-primary me-2"></i>Tempor erat elitr rebum at clita
                                </div>
                                <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <i class="fa fa-check text-primary me-2"></i>Explore More
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- About End -->
                

        <!-- New Products Start -->
        <div class="featured-category category wow fadeInUp" data-wow-delay="0.1s">
            <div class="container-fluid">
                <div class="container">
                    <div class="section-header" id="products">
                        <h2>Our Services</h2>
                    </div>
                    <div class="row">
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/roof-mounting.jpg" alt="Roof Mounting">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-solar-panel fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Solar Panel Roof Mounting</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/ground-mounting.jpg" alt="Panel Ground Mounting">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-wind fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Solar Panel Ground Mounting</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/parking.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-lightbulb fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Solar Panel Mounting Parking</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/rv.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-solar-panel fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Solar Panel Mounting RV</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/floating-mounting.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-wind fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Floating Solar Panel Mounting</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/automatic-mounting.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-lightbulb fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Solar Tracker Automatic Mount</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/screw-pile.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-lightbulb fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Ground Screw Pile</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/accessories.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-lightbulb fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Solar Panel Mounting Accessories</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="service-item rounded overflow-hidden">
                                <img class="img-fluid" src="image/service/power-system.jpg" alt="">
                                <div class="position-relative p-4 pt-0">
                                    <div class="service-icon">
                                        <i class="fa fa-lightbulb fa-3x"></i>
                                    </div>
                                    <h4 class="mb-3">Complete Set Solar Power System</h4>
                                    <p>Stet stet justo dolor sed duo. Ut clita sea sit ipsum diam lorem diam.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>            
        <!-- New Products Start -->
        
        <!-- Choose Us Start -->
        <div class="container-xxl py-5">
            <div class="container about">
                <div class="row">
                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 wow fadeIn" data-wow-delay="0.1s">
                        <div class="p-lg-5 pe-lg-0">
                            <h6 class="text-primary">Why Choose Us!</h6>
                            <h2 class="mb-4">Complete Commercial & Residential Solar Systems</h2>
                            <p>Since 2006,With over 15years manufacturer of solar mounting system experience ,Solar Energy 
                                continue to make the world more greener! Your Best Choice !Your Reliable Supplier!</p>
                            <div class="container-item">  
                                <div class="row">
                                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                                        <div class="d-flex align-items-center item-feature">
                                            <div class="btn-lg-square bg-primary rounded-circle">
                                                <i class="fa fa-check text-white"></i>
                                            </div>
                                            <div class="ms-4">
                                                <p class="mb-0">Quality</p>
                                                <h5 class="mb-0 item-text">Services</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                                        <div class="d-flex align-items-center item-feature">
                                            <div class="btn-lg-square bg-primary rounded-circle">
                                                <i class="fa fa-user-check text-white"></i>
                                            </div>
                                            <div class="ms-4">
                                                <p class="mb-0">Expert</p>
                                                <h5 class="mb-0 item-text">Workers</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                                        <div class="d-flex align-items-center item-feature">
                                            <div class="btn-lg-square bg-primary rounded-circle">
                                                <i class="fa fa-drafting-compass text-white"></i>
                                            </div>
                                            <div class="ms-4">
                                                <p class="mb-0">Free</p>
                                                <h5 class="mb-0 item-text">Consultation</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-6">
                                        <div class="d-flex align-items-center item-feature">
                                            <div class="btn-lg-square bg-primary rounded-circle">
                                                <i class="fa fa-headphones text-white"></i>
                                            </div>
                                            <div class="ms-4">
                                                <p class="mb-0">Customer</p>
                                                <h5 class="mb-0 item-text">Support</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>    
                        </div>
                    </div>
                    <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="img-about-top">
                            <div class="position-relative overflow-hidden ps-5 pt-5 h-100 us-item" style="min-height: 400px;">
                                <img class="position-absolute w-100 h-100" src="image/slider/choose.jpg" alt="Why Choose Us" style="object-fit: cover;">
                                <div class="position-absolute top-0 start-0 bg-white pe-3 pb-3" style="width: 200px; height: 200px;">
                                    <div class="d-flex flex-column justify-content-center text-center bg-primary bg-primary-us h-100 p-3">
                                        <h1 class="text-white">25</h1>
                                        <h2 class="text-white">Years</h2>
                                        <h5 class="text-white mb-0">Experience</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Choose Us End -->
            

        <!-- Contact Start -->
        <div class="featured-category category wow fadeInUp ftco-section ftco-intro" data-wow-delay="0.1s" style="background-image: url(image/slider/slider1.png);">
            <div class="overlay overlay-bg"></div>
            <div class="container-fluid">
                <div class="container">
                    <div class="section-header-contact" id="contact">
                        <h2>Contact</h2>
                    </div>
                    <div class="row">
                        <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6 col-sm-12">
                            <div class="contact-info">
                                <h2>Our Office</h2>
                                <h3><i class="fa fa-map-marker"></i>203 Fake St. Mountain View, <br> 11378 New York</h3>
                                <h3><i class="fa fa-envelope"></i>solarenergy@example.com</h3>
                                <h3><i class="fa fa-phone"></i>+012-345-6789</h3>
                                <div class="social">
                                    <a href=""><i class="fab fa-twitter"></i></a>
                                    <a href=""><i class="fab fa-facebook-f"></i></a>
                                    <a href=""><i class="fab fa-linkedin-in"></i></a>
                                    <a href=""><i class="fab fa-instagram"></i></a>
                                    <a href=""><i class="fab fa-youtube"></i></a>
                                </div>
                            </div> 
                        </div>
                        <div class="col-xxl-8 col-xl-8 col-lg-12 col-md-12 col-sm-12">
                            <div class="contact-form">
                                <form action="mail.php" method="POST">
                                    <div class="row">
                                        <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <input type="text" class="form-control" placeholder="Your Name" />
                                        </div>
                                        <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                                            <input type="email" class="form-control" placeholder="Your Email" />
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <input type="text" class="form-control" placeholder="Subject" />
                                    </div>
                                    <div class="form-group">
                                        <textarea class="form-control" rows="5" placeholder="Message"></textarea>
                                    </div>
                                    <div><button class="btn btn-primary" type="submit">Send Message</button></div>
                                </form>
                            </div> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Contact End --> 
        
        <!-- New Products Start -->
        <div class="featured-category category wow fadeInUp" data-wow-delay="0.1s">
            <div class="container-fluid">
                <div class="container">
                    <div class="section-header" id="project">
                        <h2>Our Projects</h2>
                    </div>
                    <div class="row">
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12  portfolio-item first">
                            <div class="portfolio-img rounded overflow-hidden">
                                <img class="img-fluid" src="image/project/project1.jpg" alt="">
                                <div class="portfolio-btn">                                  
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-primary mb-0">China, Yunnan, Jianshui</p>
                                <hr class="text-primary w-25 my-2">
                                <h5 class="lh-base">300MW | Complicated topography</h5>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12  portfolio-item first">
                            <div class="portfolio-img rounded overflow-hidden">
                                <img class="img-fluid" src="image/project/project2.jpg" alt="">
                                <div class="portfolio-btn">
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-primary mb-0">Mexico, Zacatecas</p>
                                <hr class="text-primary w-25 my-2">
                                <h5 class="lh-base">104MW | Ground-mounted</h5>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12  portfolio-item first">
                            <div class="portfolio-img rounded overflow-hidden">
                                <img class="img-fluid" src="image/project/project3.jpg" alt="">
                                <div class="portfolio-btn">
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-primary mb-0">Colombia, Los Llanos</p>
                                <hr class="text-primary w-25 my-2">
                                <h5 class="lh-base">81.7MW | Ground-mounted</h5>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12  portfolio-item first">
                            <div class="portfolio-img rounded overflow-hidden">
                                <img class="img-fluid" src="image/project/project4.jpg" alt="">
                                <div class="portfolio-btn">
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-primary mb-0">Vietnam, Phong Phu</p>
                                <hr class="text-primary w-25 my-2">
                                <h5 class="lh-base">42MW | Ground-mounted</h5>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12  portfolio-item first">
                            <div class="portfolio-img rounded overflow-hidden">
                                <img class="img-fluid" src="image/project/project5.jpg" alt="">
                                <div class="portfolio-btn">
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-primary mb-0">Australia, Queensland</p>
                                <hr class="text-primary w-25 my-2">
                                <h5 class="lh-base">1.22MW | Rooftop Project</h5>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-6 col-sm-12  portfolio-item first">
                            <div class="portfolio-img rounded overflow-hidden">
                                <img class="img-fluid" src="image/project/project6.jpg" alt="">
                                <div class="portfolio-btn">
                                </div>
                            </div>
                            <div class="pt-3">
                                <p class="text-primary mb-0">Greece, S. Aether</p>
                                <hr class="text-primary w-25 my-2">
                                <h5 class="lh-base">8.45MW | Ground-mounted</h5>
                            </div>
                        </div>                       
                    </div>
                </div>
            </div>
        </div>            
        <!-- New Products Start -->            
        <!-- New Products Start -->
                        <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                            <div class="message">
                            <?php $name = $_POST['name'];
                                $email = $_POST['email'];
                                $message = $_POST['message'];
                                $formcontent="From: $name \n Message: $message";
                                $recipient = "coffeeshop@coffeeshop.lanams.eu";
                                $subject = "Contact Form";
                                $mailheader = "From: $email \r\n";
                                mail($recipient, $subject, $formcontent, $mailheader) or die("Error!");
                                echo '<script>alert("Thank you for your contact")</script>';
                                echo '<script type="text/javascript">
                                       window.location = "https://solarenergypage.lanams.eu"
                                  </script>';
                                ?> 
                            </div>
                        </div>
                        


        <script src="jquery/jquery-3.6.0.min.js"></script>
        <script src="js/scroll.js"></script>
        <script src="js/slick.js"></script>
        <script src="bootstrap/js/bootstrap.bundle.js"></script>
        <script src="lib/wow/wow.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/counterup/counterup.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <script src="js/main.js"></script>

        <script>
            new WOW().init();
        </script>
