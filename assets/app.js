// assets/app.js
import './styles/app.scss';
import 'bootstrap'; // Import Bootstrap's JavaScript

// Initialize Bootstrap tooltips (or other components as needed)
import { Tooltip } from 'bootstrap';
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new Tooltip(tooltipTriggerEl)
    })
});

console.log('Webpack Encore with Bootstrap is working!');
