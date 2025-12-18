// deleteRecord.js
const { Wallets, Gateway } = require('fabric-network');
const fs = require('fs');
const path = require('path');

async function deleteViolationRecord(adminID, violationID) {
  try {
    let ccpPath;
    // Choose connection profile based on adminID. This example assumes Org1 for simplicity.
    if (adminID.includes('org2')) {
      ccpPath = path.resolve(__dirname, 'network', 'connection-org2.json');
    } else {
      ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
    }
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);

    const identity = await wallet.get(adminID);
    if (!identity) {
      throw new Error(`Admin identity ${adminID} not found in wallet.`);
    }

    const gateway = new Gateway();
    await gateway.connect(ccp, { wallet, identity: adminID, discovery: { enabled: true, asLocalhost: true } });
    const network = await gateway.getNetwork('mychannel');
    const contract = network.getContract('trafficviolation');
    await contract.submitTransaction('DeleteViolation', violationID);
    await gateway.disconnect();
  } catch (error) {
    throw error;
  }
}

module.exports = { deleteViolationRecord };
