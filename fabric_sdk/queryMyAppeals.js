// queryMyAppeals.js
const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');

async function queryMyAppeals(driverID) {
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

        const result = await contract.evaluateTransaction('QueryMyAppeals');
        // console.log(`QueryMyAppeals result: ${result.toString()}`);

        await gateway.disconnect();
        return JSON.parse(result.toString());
    } catch (error) {
        throw error;
    }
}

module.exports = { queryMyAppeals };
