<?php
session_start();

// Function to check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Function to check if user is a worker (approved or not)
function isWorker()
{
    return isset($_SESSION['role']) && $_SESSION['role'] == 'worker';
}

// Function to check if user is an approved worker
function isApprovedWorker()
{
    return isset($_SESSION['role']) && $_SESSION['role'] == 'worker' && isset($_SESSION['status']) && $_SESSION['status'] == 'approved';
}

// Redirect if not logged in
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Redirect if not admin
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        header("Location: index.php?error=unauthorized");
        exit;
    }
}

// Redirect if not approved worker or admin
function requireApproved()
{
    requireLogin();
    if (!isAdmin() && !isApprovedWorker()) {
        header("Location: index.php?error=pending_approval");
        exit;
    }
}