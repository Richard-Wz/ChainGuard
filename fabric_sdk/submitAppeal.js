// submitAppeal.js
const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');

async function submitAppeal(driverID, appealID, violationID, appealText, evidence, timestamp) {
try {
    // Load connection profile
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org2.json');
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

    // Setup wallet
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);

    // Ensure driver identity exists in wallet
    const identity = await wallet.get(driverID);
    if (!identity) {
        throw new Error(`Driver identity ${driverID} not found in wallet.`);
    }

    // Create a new gateway for connecting to our peer node.
    const gateway = new Gateway();
    await gateway.connect(ccp, { wallet, identity: driverID, discovery: { enabled: true, asLocalhost: true } });

    // Get the network (channel) our contract is deployed to.
    const network = await gateway.getNetwork('mychannel');

    // Get the contract from the network.
    const contract = network.getContract('trafficviolation');

    // Submit the transaction (chaincode function: SubmitAppeal)
    await contract.submitTransaction('SubmitAppeal', appealID, violationID, appealText, evidence, timestamp);
    // console.log(`Appeal ${appealID} submitted successfully`);

    // Disconnect from the gateway.
    await gateway.disconnect();
    } catch (error) {
        console.error(`Failed to submit appeal: ${error}`);
        throw error;
    }
}

module.exports = { submitAppeal };
