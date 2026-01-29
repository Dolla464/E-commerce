# 🛒 E-Commerce RESTful API

A comprehensive E-commerce Backend system built with **Laravel**, designed for learning and implementing advanced web development concepts. This project focuses on building a robust, secure, and well-documented API.

---

## 🚀 Key Features
* **RESTful API Architecture:** Clean and scalable API endpoints.
* **Payment Gateway Integration:** Integrated with **Stripe** for handling secure transactions.
* **API Documentation:** Fully documented using **Swagger (L5-Swagger)** for easy testing.
* **Notification System:** Built-in system for user alerts and updates.
* **Database Management:** Efficient use of Migrations, Factories, and Seeders.
* **Queue Management:** Background job processing using the database driver to improve performance.

## 🛠 Tech Stack
* **Framework:** Laravel 11.x
* **Database:** MySQL
* **Documentation:** Swagger UI
* **Payments:** Stripe API
* **Server:** PHP 8.2+

## 📖 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/YOUR_USERNAME/e-commerce.git](https://github.com/YOUR_USERNAME/e-commerce.git)
   cd e-commerce
   
2. **Install dependencies:**
    composer install

3. **Environment Setup:**
    - Copy the example environment file:
        cp .env.example .env
    - Generate the application key:
        php artisan key:generate
    - Configure your database settings in the .env file.

4. **Run Migrations:**
    php artisan migrate

5. **Serve the Application:**
    php artisan serve

📝 API Documentation
Once the server is running, you can explore and test the API endpoints via Swagger UI at: http://127.0.0.1:8000/api/documentation

🔒 Security Note
This project is for educational purposes. Ensure that all sensitive keys (like Stripe Secret Keys) are stored in the .env file and never committed to version control.

