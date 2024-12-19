<?php

session_start();

// Check if user is not logged in, redirect to login page
if (!isset($_SESSION['username'])) {
    header("Location: \login_crm.php");
    exit;
}

include('include/db_connection.php');
include('include/header.php');

// Initialize variables
$num_rows = 0;
$result = null;

// Check if form is submitted
if (isset($_GET['submit'])) {
    
    $student_crn = isset($_GET['student_crn']) ? mysqli_real_escape_string($conn, $_GET['student_crn']) : '';
    $firm_name = isset($_GET['firm_name']) ? mysqli_real_escape_string($conn, $_GET['firm_name']) : '';

    $sql = "SELECT DISTINCT student_crn, name_of_student, student_gender, 
    z.training_organization, city_name, period Registered_Base, 
     DATE_FORMAT(date_of_comm, '%d-%m-%Y') date_of_comm,
      DATE_FORMAT(date_of_compl, '%d-%m-%Y') date_of_compl,

        audit_assurance_related_services, accounting_financial_reporting, taxation_corporate_law, financial_management_management_advisory_it,
        any_other_appropriate_area,
        audit_assurance_related_services + accounting_financial_reporting + taxation_corporate_law + financial_management_management_advisory_it +
        (any_other_appropriate_area) total    
    FROM 
    (
    SELECT 
        d.student_crn, 
        d.name_of_student,
        d.student_gender,
        h.period,
        DATE(e.to_date) AS date_of_comm,
        DATE(e.from_date) AS date_of_compl,
        DATEDIFF(e.to_date, e.from_date) AS dateif_col,
        ROUND(DATEDIFF(e.to_date, e.from_date) / 3) AS dateif_col_3,
        CASE WHEN b.audit_assurance_related_services = 'true' THEN 1 ELSE 0 END / DATEDIFF(e.to_date, e.from_date) AS dateif_col_4,
        c.training_organization,
        g.name AS city_name,  
        b.record_date, 
        b.name_of_the_client, 
        e.contract_base,
        ROUND(SUM(CASE WHEN b.audit_assurance_related_services = 'true' THEN 1 ELSE 0 END) / ROUND(DATEDIFF(e.to_date, e.from_date) / 3) * 100,2) AS audit_assurance_related_services,
        ROUND(SUM(CASE WHEN b.accounting_financial_reporting = 'true' THEN 1 ELSE 0 END) / ROUND(DATEDIFF(e.to_date, e.from_date) / 3) * 100,2) AS accounting_financial_reporting,
        ROUND(SUM(CASE WHEN b.taxation_corporate_law = 'true' THEN 1 ELSE 0 END) / ROUND(DATEDIFF(e.to_date, e.from_date) / 3) * 100,2) AS taxation_corporate_law,
        ROUND(SUM(CASE WHEN b.financial_management_management_advisory_it = 'true' THEN 1 ELSE 0 END) / ROUND(DATEDIFF(e.to_date, e.from_date) / 3) * 100,2) AS financial_management_management_advisory_it,
        ROUND(SUM(CASE WHEN b.any_other_appropriate_area = 'true' THEN 1 ELSE 0 END) / ROUND(DATEDIFF(e.to_date, e.from_date) / 3) * 100,2) AS any_other_appropriate_area

    FROM 
        crm_firms_tos_students_daily_records a
        
    INNER JOIN 
        crm_firms_tos_students_daily_records_details b ON a.id = b.daily_record_id
    INNER JOIN 
        crm_firms_tos c ON c.id = a.to_id
    INNER JOIN 
        crm_students d ON d.id = a.student_id
    INNER JOIN
        crm_firms_offices f ON f.firm_id = c.firm_id 
    INNER JOIN
        cities g ON g.id = f.city
    LEFT OUTER JOIN 
        crm_firms_tos_students_contracts e ON d.id = e.student_id
    LEFT JOIN
        crm_firms_tos_contracts_base h ON h.id = e.contract_base

    GROUP BY 
        d.student_crn
    
    ) z
    WHERE 1=1";

    // Add condition for student_crn
    if ($student_crn != '') {
        $sql .= " AND student_crn = '$student_crn'";
    }
    if (!empty($firm_name)) {
        $sql .= " AND z.training_organization LIKE '%$firm_name%'";
    }

    $sql .= " ORDER BY training_organization";

    // Query database
    $result = $conn->query($sql);

    // Get the number of rows returned by the query
    $num_rows = $result->num_rows;
}
?>

<!DOCTYPE html>
<html>
<head>
  <script type="text/javascript" src="https://unpkg.com/xlsx@0.15.1/dist/xlsx.full.min.js"></script> <!-- export html table to excel-->
    <title>ICAP</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<h2>Trainee Student wise Training Areas covered by Trainee as of </h2>

<form method="GET" action="">
    <label for="student_crn">CRN:</label>
    <input type="text" id="student_crn" name="student_crn" value="<?php echo isset($_GET['student_crn']) ? htmlspecialchars($_GET['student_crn']) : ''; ?>">
    <label for="firm_name">Firm Name:</label>
    
    <select name="firm_name" id="firm_name">
        <option value="">Select Firm Name</option>
        <?php
        // Fetch firm names from database
        $firm_query = "SELECT training_organization firm_name, id FROM crm_firms_tos where is_active = 1";
        $firm_result = $conn->query($firm_query);
        if ($firm_result->num_rows > 0) {
            while ($firm_row = $firm_result->fetch_assoc()) {
                $selected = isset($_GET['firm_name']) && $_GET['firm_name'] == $firm_row['firm_name'] ? 'selected' : '';
                echo "<option value='" . $firm_row['firm_name'] . "' $selected>" . $firm_row['firm_name'] . "</option>";
            }
        }
        ?>
    </select>

    <input type="submit" name="submit" value="Submit">
    <button onClick="ExportToExcel('xlsx')" class="btn btn-lg export_btn"> <i class="fa fa-download" aria-hidden="true"></i> Export Data</button>
</form>

<?php

if (isset($result)) {
    echo "<p>Number of rows: $num_rows</p>";
    if ($num_rows > 0) {
        // Output data of each row
        echo "<table id='tbl_exporttable_to_xls'>";
        echo "<tr>";
        echo "<th>CRN</th>";
        echo "<th>Student Name</th>";
        echo "<th>Gender</th>";
        echo "<th>Training Organization</th>";
        echo "<th>City</th>";
        echo "<th>Basis</th>";
        echo "<th>Date of Commencement</th>";
        echo "<th>Date of Completion</th>";
       
        echo "<th>Audit Assurance Related Services</th>";
        echo "<th>Accounting Financial Reporting</th>";
        echo "<th>Taxation Corporate Law</th>";
        echo "<th>Financial Management Management Advisory IT</th>";
        echo "<th>Any Other Appropriate Area</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>".$row["student_crn"]."</td>";
            echo "<td>".$row["name_of_student"]."</td>";
            echo "<td>".$row["student_gender"]."</td>";
            echo "<td>".$row["training_organization"]."</td>";
            echo "<td>".$row["city_name"]."</td>";
            echo "<td>".$row["Registered_Base"]."</td>";
            echo "<td>".$row["date_of_compl"]."</td>";
            echo "<td>".$row["date_of_comm"]."</td>";
            
            echo "<td>".$row["audit_assurance_related_services"]."</td>";
            echo "<td>".$row["accounting_financial_reporting"]."</td>";
            echo "<td>".$row["taxation_corporate_law"]."</td>";
            echo "<td>".$row["financial_management_management_advisory_it"]."</td>";
            echo "<td>".$row["any_other_appropriate_area"]."</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No data found";
    }
}
?>

<script>
function ExportToExcel(type, fn, dl) {
    var elt = document.getElementById('tbl_exporttable_to_xls');
    var wb = XLSX.utils.table_to_book(elt, { sheet: "sheet1" });
    return dl ? XLSX.write(wb, { bookType: type, bookSST: true, type: 'base64' }) : XLSX.writeFile(wb, fn || ('ExcelData.' + (type || 'xlsx')));
}
</script>

</body>
</html>

<?php
// Close MySQL connection
$conn->close();
?>
