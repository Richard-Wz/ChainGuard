// updateViolation.js
const { Wallets, Gateway } = require('fabric-network');
const fs = require('fs');
const path = require('path');

async function updateViolationRecord(adminID, violationID, newPaymentStatus, newViolationStatus) {
    try {
        // Load the network configuration
        const ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
        const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

        // Create a new wallet and gateway connection
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

        // Get the network and contract
        const network = await gateway.getNetwork('mychannel');
        const contract = network.getContract('trafficviolation');

        // Submit the update transaction to the blockchain
        await contract.submitTransaction(
        'UpdateViolation',   // Chaincode function to update violation status
        violationID,
        newPaymentStatus.toString(),  // Convert boolean to string before sending
        newViolationStatus.toString() // Convert boolean to string before sending
        );

        // Disconnect from the gateway
        await gateway.disconnect();
    } catch (error) {
        throw error;
    }
}

module.exports = { updateViolationRecord };
