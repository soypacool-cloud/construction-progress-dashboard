# 🏗️ Construction Progress Dashboard

A portfolio demo web application for monitoring construction projects and weighted process progress.

> **Demo project:** all data is fictional. No production or confidential information is included.

## Features

- Project overview dashboard
- KPI cards for total projects, overall progress, active and completed projects
- Searchable and sortable project table
- Weighted construction progress calculation
- Project detail view
- Progress by construction process
- Register or update process progress from the web interface
- Progress history and weighted evolution chart
- Dynamic project filters by status and location
- Date-based progress history
- Input validation for progress values (0–100)
- Interactive Highcharts visualizations
- Responsive Bootstrap interface
- MySQL database integration with PDO

## Technologies

PHP · MySQL · PDO · JavaScript · Bootstrap · DataTables · Highcharts · HTML5 · CSS3

## Progress Calculation

Each construction process has a weight. The contribution of a process is calculated as:

`process contribution = process weight × executed progress / 100`

The project progress is the sum of the contributions of its active processes.

## Local Installation

1. Create the `construction_dashboard` database.
2. Import `database/database.sql`.
3. Copy `config/database.example.php` to `config/database.php`.
4. Set your local MySQL credentials.
5. Place the project in your XAMPP `htdocs` directory.
6. Open:

`http://localhost/construction-progress-dashboard/public/`

## Portfolio

This project demonstrates practical experience in business applications, database-driven systems, dashboards, data visualization, and business logic.

## Author

**Paco Ruiz**  
Systems Engineer · Full Stack Developer · IT Administrator
