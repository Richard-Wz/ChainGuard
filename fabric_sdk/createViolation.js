// createViolation.js
const { Wallets, Gateway } = require('fabric-network');
const fs = require('fs');
const path = require('path');

async function createViolationRecord(
  adminID,
  violationID,
  driverID,
  violationType,
  location,
  penaltyAmount,
  licensePlateNumber,
  timestamp,
  image,
  remark,
  paymentStatus,
  violationStatus
) {
  try {
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);

    const identity = await wallet.get(adminID);
    if (!identity) {
      throw new Error(`Admin identity ${adminID} not found in wallet.`);
    }

    const gateway = new Gateway();
    await gateway.connect(ccp, {
      wallet,
      identity: adminID,
      discovery: { enabled: true, asLocalhost: true }
    });

    const network = await gateway.getNetwork('mychannel');
    const contract = network.getContract('trafficviolation');
    await contract.submitTransaction(
      'CreateViolation',
      violationID,
      driverID,
      violationType,
      location,
      penaltyAmount.toString(),
      licensePlateNumber,
      timestamp,
      image,
      remark,
      paymentStatus.toString(),
      violationStatus.toString()
    );
    await gateway.disconnect();
  } catch (error) {
    throw error;
  }
}

module.exports = { createViolationRecord };