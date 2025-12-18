// fabricHelper.js
const { Wallets, Gateway } = require('fabric-network');
const fs = require('fs');
const path = require('path');

/**
 * Retrieves the actual client ID by calling the GetClientID chaincode function.
 * @param {string} userID - The user identity label in the wallet.
 * @returns {Promise<string>} The client ID as returned by the chaincode.
 */
async function getClientId(userID) {
try {
    // Load the connection profile for Org2 (adjust if using a different org)
    const ccpPath = path.resolve(__dirname, 'network', 'connection-org2.json');
    const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

    // Load the wallet directory
    const walletPath = path.join(process.cwd(), 'wallet');
    const wallet = await Wallets.newFileSystemWallet(walletPath);

    // Check that the identity exists in the wallet
    const identity = await wallet.get(userID);
    if (!identity) {
        throw new Error(`An identity for the user ${userID} does not exist in the wallet`);
    }

    // Create a new gateway connection
    const gateway = new Gateway();
    await gateway.connect(ccp, {
        wallet,
        identity: userID,
        discovery: { enabled: true, asLocalhost: true } // adjust for your network settings
    });

    // Get the channel network and contract instance
    const network = await gateway.getNetwork('mychannel');
    const contract = network.getContract('trafficviolation');

    // Evaluate the GetClientID transaction (this chaincode function must be implemented)
    const result = await contract.evaluateTransaction('GetClientID');

    // Disconnect from the gateway when done
    await gateway.disconnect();

    return result.toString();
    } catch (error) {
        console.error(`Failed to get client ID: ${error}`);
        throw error;
    }
}

module.exports = { getClientId };
