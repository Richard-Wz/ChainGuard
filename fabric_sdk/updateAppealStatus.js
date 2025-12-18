// updateAppealStatus.js
const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');

async function updateAppealStatus(adminID, appealID, newStatus) {
try {
    // Load connection profile
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

    // Setup wallet
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);

    // Ensure admin identity exists in wallet
    const identity = await wallet.get(adminID);
    if (!identity) {
        throw new Error(`Admin identity ${adminID} not found in wallet.`);
    }

    // Create a new gateway for connecting to our peer node.
    const gateway = new Gateway();
    await gateway.connect(ccp, { wallet, identity: adminID, discovery: { enabled: true, asLocalhost: true } });

    // Get the network (channel) our contract is deployed to.
    const network = await gateway.getNetwork('mychannel');

    // Get the contract from the network.
    const contract = network.getContract('trafficviolation');

    // Submit the transaction (chaincode function: UpdateAppealStatus)
    await contract.submitTransaction('UpdateAppealStatus', appealID, newStatus);
    console.log(`Appeal ${appealID} status updated to ${newStatus}`);

    await gateway.disconnect();
    } catch (error) {
    console.error(`Failed to update appeal status: ${error}`);
    throw error;
    }
}

module.exports = { updateAppealStatus };
