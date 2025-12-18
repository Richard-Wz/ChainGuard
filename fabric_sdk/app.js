// app.js
const express = require('express');
const bodyParser = require('body-parser');
const { registerAndRetrieveClientId } = require('./integratedUser');
const { createViolationRecord } = require('./createViolation');
const { deleteViolationRecord } = require('./deleteRecord');
const { queryAllViolations } = require('./queryAllTransaction');
const { queryMyViolations } = require('./queryMyTransaction');
const { updateViolationRecord } = require('./updateViolation');

// New functions for the appeal system
const { submitAppeal } = require('./submitAppeal');
const { queryMyAppeals } = require('./queryMyAppeals');
const { queryAllAppeals } = require('./queryAllAppeals');
const { updateAppealStatus } = require('./updateAppealStatus');
const { deleteAppealRecord } = require('./deleteAppeal');

const app = express();
app.use(bodyParser.json({ limit: '50mb' }));

// Endpoint for registration
app.post('/api/register', async (req, res) => {
  try {
    const { org, role } = req.body;
    if (!org || !role) {
      return res.status(400).json({ error: 'Please provide both org and role.' });
    }
    const result = await registerAndRetrieveClientId(org, role);
    const logResult = {
      userID: result.userID,
      clientID: result.clientID,
      org: org,
      role: role,
    }
    console.log('Registration result:', JSON.stringify(logResult, null, 2));
    res.status(200).json(result);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for creating a violation record
app.post('/api/createViolation', async (req, res) => {
  try {
    const {
      adminID,
      violationID,
      driverID,
      violationType,
      location,
      penaltyAmount,
      timestamp,
      licensePlateNumber,
      image,
      remark,
      paymentStatus,
      violationStatus
    } = req.body;
    
    // Check for missing parameters 
    if (
      !adminID || !violationID || !driverID || !violationType ||
      !location || penaltyAmount === undefined || !licensePlateNumber || timestamp === undefined ||
      image === undefined || remark === undefined ||
      paymentStatus === undefined || violationStatus === undefined
    ) {
      return res.status(400).json({ error: 'Missing parameters' });
    }
    
    await createViolationRecord(
      adminID,
      violationID,
      driverID,
      violationType,
      location,
      penaltyAmount,
      timestamp,
      licensePlateNumber,
      image,
      remark,
      paymentStatus,
      violationStatus
    );
    res.status(200).json({ message: `Violation record "${violationID}" created successfully.` });
    console.log(`Violation record "${violationID}" created successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for querying all violations (admin)
app.get('/api/queryAllViolations', async (req, res) => {
  try {
    const { adminID } = req.query;
    if (!adminID) {
      return res.status(400).json({ error: 'Please provide adminID' });
    }
    const result = await queryAllViolations(adminID);
    res.status(200).json(result);
    console.log('All violations queried successfully.');
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for querying my violations (driver)
app.get('/api/queryMyViolations', async (req, res) => {
  try {
    const { driverID } = req.query;
    if (!driverID) {
      return res.status(400).json({ error: 'Please provide driverID' });
    }
    const result = await queryMyViolations(driverID);
    res.status(200).json(result);
    console.log(`"${driverID}" violations queried successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for deleting a violation record
app.delete('/api/deleteViolation', async (req, res) => {
  try {
    const { adminID, violationID } = req.query;
    if (!adminID || !violationID) {
      return res.status(400).json({ error: 'Please provide adminID and violationID' });
    }
    await deleteViolationRecord(adminID, violationID);
    res.status(200).json({ message: `Violation record "${violationID}" deleted successfully.` });
    console.log(`Violation record "${violationID}" deleted`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint to update violation status
app.put('/api/updateViolation', async (req, res) => {
  try {
    const { adminID, violationID, newPaymentStatus, newViolationStatus } = req.body;

    // Check for missing parameters
    if (!adminID || !violationID || newPaymentStatus === undefined || newViolationStatus === undefined) {
      return res.status(400).json({ error: 'Missing parameters' });
    }

    await updateViolationRecord(adminID, violationID, newPaymentStatus, newViolationStatus);
    res.status(200).json({ message: `Violation record "${violationID}" updated successfully.` });
    console.log(`Violation record "${violationID}" updated successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for submitting an appeal (driver)
app.post('/api/submitAppeal', async (req, res) => {
  try {
    const { driverID, appealID, violationID, appealText, evidence, timestamp } = req.body;
    // Check for missing parameters
    if (!driverID || !appealID || !violationID || !appealText || !timestamp) {
      return res.status(400).json({ error: 'Missing parameters for appeal submission' });
    }
    await submitAppeal(driverID, appealID, violationID, appealText, evidence, timestamp);
    res.status(200).json({ message: `Appeal "${appealID}" submitted successfully.` });
    console.log(`Appeal "${appealID}" submitted successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for querying my appeals (driver)
app.get('/api/queryMyAppeals', async (req, res) => {
  try {
    const { driverID } = req.query;
    if (!driverID) {
      return res.status(400).json({ error: 'Please provide driverID' });
    }
    const result = await queryMyAppeals(driverID);
    res.status(200).json(result);
    console.log(`Appeals for driver "${driverID}" queried successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for querying all appeals (admin)
app.get('/api/queryAllAppeals', async (req, res) => {
  try {
    const { adminID } = req.query;
    if (!adminID) {
      return res.status(400).json({ error: 'Please provide adminID' });
    }
    const result = await queryAllAppeals(adminID);
    res.status(200).json(result);
    console.log('All appeals queried successfully.');
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for updating appeal status (admin)
app.put('/api/updateAppealStatus', async (req, res) => {
  try {
    const { adminID, appealID, newStatus } = req.body;
    if (!adminID || !appealID || !newStatus) {
      return res.status(400).json({ error: 'Missing parameters for updating appeal status' });
    }
    await updateAppealStatus(adminID, appealID, newStatus);
    res.status(200).json({ message: `Appeal "${appealID}" status updated successfully.` });
    console.log(`Appeal "${appealID}" status updated successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});


// Endpoint for deleting all violations for a given driver
app.delete('/api/deleteAllViolationsForDriver', async (req, res) => {
  try {
    const { adminID, driverID } = req.query;
    if (!adminID || !driverID) {
      return res.status(400).json({ error: 'Please provide both adminID and driverID' });
    }
    
    // Query all violations as admin.
    // This endpoint is admin-only, so we assume adminID is valid.
    const allViolations = await queryAllViolations(adminID);
    
    // Filter violations matching the specified driverID.
    const driverViolations = allViolations.filter(v => v.driverID === driverID);
    
    // Iterate over each violation and delete it.
    for (const violation of driverViolations) {
      await deleteViolationRecord(adminID, violation.violationID);
    }
    
    res.status(200).json({message: `Deleted ${driverViolations.length} violations for driver ${driverID}.`});
    console.log(`Deleted ${driverViolations.length} violations for driver ${driverID}.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

// Endpoint for deleting all appeals for a given driver
app.delete('/api/deleteAllAppealsForDriver', async (req, res) => {
  try {
    const { adminID, driverID } = req.query;
    if (!adminID || !driverID) {
      return res.status(400).json({ error: 'Please provide both adminID and driverID' });
    }
    
    // Query all appeals as admin
    const allAppeals = await queryAllAppeals(adminID);
    
    // Filter appeals matching the specified driverID
    const driverAppeals = allAppeals.filter(a => a.driverID === driverID);
    
    // Iterate over each appeal and delete it
    for (const appeal of driverAppeals) {
      await deleteAppealRecord(adminID, appeal.appealID);
    }
    
    res.status(200).json({message: `Deleted ${driverAppeals.length} appeals for driver ${driverID}.`});
    console.log(`Deleted ${driverAppeals.length} appeals for driver ${driverID}.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

app.get('/api/getClientId', async (req, res) => {
  try {
    const { userID } = req.query;
    if (!userID) {
      return res.status(400).json({ error: 'Please provide userID.' });
    }
    const clientId = await getClientId(userID);
    res.status(200).json({ clientId });
    console.log(`Client ID for ${userID} retrieved successfully.`);
  } catch (error) {
    res.status(500).json({ error: error.toString() });
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`API server running on port ${PORT}`);
});
