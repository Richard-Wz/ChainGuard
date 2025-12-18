// queryMyTransaction.js
const { Wallets, Gateway } = require('fabric-network');
const fs = require('fs');
const path = require('path');

async function queryMyViolations(driverID) {
  try {
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org2.json'); // Assuming drivers belong to Org2
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);
    const identity = await wallet.get(driverID);
    if (!identity) {
      throw new Error(`Driver identity ${driverID} not found in wallet.`);
    }
    const gateway = new Gateway();
    await gateway.connect(ccp, { wallet, identity: driverID, discovery: { enabled: true, asLocalhost: true } });
    const network = await gateway.getNetwork('mychannel');
    const contract = network.getContract('trafficviolation');
    const result = await contract.evaluateTransaction('QueryMyViolations');
    await gateway.disconnect();
    return JSON.parse(result.toString());
  } catch (error) {
    throw error;
  }
}

module.exports = { queryMyViolations };
