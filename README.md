# Expense Tracker

This app is aimed at every single people who wants to spend money wisely!

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Getting Started](#getting-started)
    - [Prerequisites](#prerequisites)
    - [Installation](#installation)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

## Overview

I have the habit of spending money anywhere without knowing the need to spend. So, I created app for myself
so that I could keep track of my spending's and only spend on the things that really matters.

I solved the above problem by keeping track of every expenditure. Addition of expense is super easy and I filter my
expenses, view the information on my dashboard and act as per the information provided by my app.

## Features

List the key features of your app here. You can use bullet points or a numbered list.

- Easy addition of expense
- Easy searching, sorting, and filtering

## Getting Started

To get your app up and running, follow these steps:

### Prerequisites

Before you begin, ensure you have the following prerequisites installed:

- [PHP](https://www.php.net/downloads.php)
- [Composer](https://getcomposer.org/download/)
- [Node.js](https://nodejs.org/)
- npm (npm is included with Node.js. However, you can update it by running `npm install -g npm`)
- [MySQL](https://www.mysql.com/) or [MariaDB](https://mariadb.org/)

### Installation

1. Clone the repository: `git clone https://github.com/decodesaurav/expense_tracker_app.git`
2. Navigate to the project directory: `cd your-app`
3. Install dependencies: `npm install`
4. Install Laravel Auth: `composer require laravel/ui`, `php artisan ui bootstrap --auth`
5. **Copy .env File**
6. **Generate Application Key**

### Database Migration

1. **Create and Configure Database:**
   Create a database in MySQL/MariaDB and update the `.env` file with the database connection details.

2. **Run Migrations:** `php artisan migrate`

This will create the necessary database tables based on your migration files.

## Running the application

1. **Development Server:**
   To run the development server, use the following command: `php artisan serve`

## Contributing

Explain how others can contribute to your project. Include guidelines for submitting pull requests and reporting issues.
